<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\User;
use App\Services\Payments\KhaltiGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payment orchestration shared by every method. Gateways only talk to the provider;
 * this class decides what a completed or failed payment means for the order.
 *
 * complete() is idempotent: the payment row is locked and a second call for the same
 * payment (double callback, refresh, two admins clicking) changes nothing.
 */
class PaymentService
{
    public function __construct(
        private InventoryService $inventory,
        private InvoiceService $invoices,
        private AccountingService $accounting,
    ) {
    }

    public function khalti(): KhaltiGateway
    {
        return app(KhaltiGateway::class);
    }

    /** The open payment of an order for its chosen method, created if missing. */
    public function openPayment(Order $order): Payment
    {
        return $order->payments()
            ->where('method', $order->payment_method)
            ->whereIn('status', ['initiated', 'failed', 'cancelled'])
            ->first()
            ?? Payment::create([
                'order_id' => $order->id,
                'method' => $order->payment_method,
                'amount' => $order->balanceDue(),
                'currency' => $order->currency,
                'status' => 'initiated',
            ]);
    }

    /** A QR or bank payment the shopper says they made. Waits for the shop to check it. */
    public function submitManual(Order $order, ?string $reference, ?string $proofPath): Payment
    {
        return DB::transaction(function () use ($order, $reference, $proofPath) {
            $payment = $this->openPayment($order);
            $payment->update([
                'status' => 'pending_verification',
                'payer_reference' => $reference,
                'proof_path' => $proofPath ?? $payment->proof_path,
                'amount' => $order->balanceDue(),
            ]);

            $order->update(['payment_status' => 'pending_verification']);

            // Keep the stock held while the shop verifies.
            $order->reservations()->where('status', 'active')->update(['expires_at' => null]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'to_status' => 'pending_verification',
                'note' => 'Payment submitted'.($reference ? " (ref: {$reference})" : '').'. The shop will verify it shortly.',
            ]);

            $this->log($payment, 'manual.submitted', ['reference' => $reference]);

            return $payment;
        });
    }

    public function complete(Payment $payment, ?string $transactionId, array $response = [], ?User $verifiedBy = null): Payment
    {
        return DB::transaction(function () use ($payment, $transactionId, $response, $verifiedBy) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === 'completed') {
                $this->log($payment, 'complete.duplicate_ignored', ['transaction_id' => $transactionId]);

                return $payment;
            }

            $payment->update([
                'status' => 'completed',
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'gateway_response' => $response ?: $payment->gateway_response,
                'verified_by' => $verifiedBy?->id,
                'completed_at' => now(),
            ]);

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();
            $paid = Money::toMinor($order->paid_total) + Money::toMinor($payment->amount);
            $fullyPaid = $paid >= Money::toMinor($order->grand_total);

            $order->update([
                'paid_total' => Money::fromMinor($paid),
                'payment_status' => $fullyPaid ? 'paid' : 'unpaid',
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'to_status' => 'paid',
                'note' => $payment->methodLabel().' payment of '.Money::format($payment->amount, $payment->currency).' received.',
                'user_id' => $verifiedBy?->id,
            ]);

            if ($fullyPaid) {
                if ($order->status === 'pending') {
                    $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                    OrderStatusHistory::create([
                        'order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'confirmed',
                        'note' => 'Order confirmed after payment.', 'user_id' => $verifiedBy?->id,
                    ]);
                }

                if ($order->status !== 'cancelled') {
                    $this->inventory->commit($order);
                }

                $invoice = $this->invoices->issueFor($order->fresh());
                $this->accounting->recordSale($invoice, $order->fresh('items'));
            }

            $this->accounting->recordReceipt($payment);

            $customer = $order->customer;
            $customer?->update(['total_spent' => Money::fromMinor(Money::toMinor($customer->total_spent) + Money::toMinor($payment->amount))]);

            $this->log($payment, 'completed', ['transaction_id' => $transactionId]);

            if ($verifiedBy) {
                ActivityLog::record('payment.verified', $payment, ['order' => $order->number]);
            }

            return $payment;
        });
    }

    public function fail(Payment $payment, string $reason, array $response = [], ?User $user = null): void
    {
        DB::transaction(function () use ($payment, $reason, $response, $user) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === 'completed') {
                return;
            }

            $payment->update(['status' => 'failed', 'gateway_response' => $response ?: $payment->gateway_response]);
            $order = $payment->order;

            if (! $order->isPaid()) {
                $order->update(['payment_status' => $user ? 'failed' : 'unpaid']);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'to_status' => 'failed',
                'note' => $reason,
                'user_id' => $user?->id,
            ]);

            $this->log($payment, 'failed', ['reason' => $reason]);

            if ($user) {
                ActivityLog::record('payment.rejected', $payment, ['order' => $order->number, 'reason' => $reason]);
            }
        });
    }

    public function log(?Payment $payment, string $event, array $payload = []): void
    {
        PaymentLog::create([
            'store_id' => $payment?->store_id,
            'payment_id' => $payment?->id,
            'event' => $event,
            'payload' => $payload,
        ]);
    }
}
