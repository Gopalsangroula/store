<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Refunds: a credit note against the invoice, the money paid back (recorded; the transfer
 * itself is done in Khalti's merchant panel or the bank), and stock back on the shelf only
 * for items the shop has inspected.
 */
class RefundService
{
    public function __construct(
        private InvoiceService $invoices,
        private AccountingService $accounting,
        private InventoryService $inventory,
        private OrderService $orders,
    ) {
    }

    public function refund(Order $order, float $amount, string $reason, string $method, ?string $reference, array $restock, User $user): PaymentRefund
    {
        if (! $order->isPaid()) {
            throw new \RuntimeException('Only paid orders can be refunded. Cancel unpaid orders instead.');
        }

        $remaining = Money::toMinor($order->paid_total) - Money::toMinor($order->refunded_total);

        if (Money::toMinor($amount) <= 0 || Money::toMinor($amount) > $remaining) {
            throw new \RuntimeException('Refund must be between Rs. 0.01 and '.Money::format(Money::fromMinor($remaining)).'.');
        }

        return DB::transaction(function () use ($order, $amount, $reason, $method, $reference, $restock, $user) {
            $payment = $order->payments()->where('status', 'completed')->latest('id')->firstOrFail();
            $invoice = $order->invoice ?? $this->invoices->issueFor($order);

            $note = $this->invoices->creditNote($invoice, $amount, $reason, $user);
            $this->accounting->recordCreditNote($note);

            $refund = PaymentRefund::create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'method' => $method,
                'reference' => $reference,
                'user_id' => $user->id,
            ]);
            $this->accounting->recordRefund($refund);

            $refunded = Money::toMinor($order->refunded_total) + Money::toMinor($amount);
            $full = $refunded >= Money::toMinor($order->paid_total);

            $order->update([
                'refunded_total' => Money::fromMinor($refunded),
                'payment_status' => $full ? 'refunded' : 'partially_refunded',
            ]);

            if (array_sum($restock) > 0) {
                $this->inventory->restockReturn($order, $restock);
            }

            if ($full && in_array($order->status, ['shipped', 'delivered'], true)) {
                $order->update(['status' => 'returned']);
            }

            $this->orders->history($order, null, $full ? 'refunded' : 'partially_refunded',
                'Refund of '.Money::format($amount).' ('.$note->number.'): '.$reason, $user);

            ActivityLog::record('order.refunded', $order, ['amount' => $amount, 'credit_note' => $note->number]);

            return $refund;
        });
    }
}
