<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderStatusMail;
use Illuminate\Support\Facades\DB;

/** The order lifecycle. Status changes go through here so stock, payments and history stay in step. */
class OrderService
{
    public function __construct(private InventoryService $inventory, private PaymentService $payments)
    {
    }

    public function changeStatus(Order $order, string $status, ?string $note = null, ?User $user = null, array $extra = []): Order
    {
        if ($status === 'cancelled') {
            return $this->cancel($order, $note ?: 'Cancelled by the store.', $user);
        }

        if (! $order->canMoveTo($status)) {
            throw new \RuntimeException("An order that is {$order->statusLabel()} cannot be marked {$status}.");
        }

        DB::transaction(function () use ($order, $status, $note, $user, $extra) {
            $from = $order->status;
            $fields = ['status' => $status] + array_intersect_key($extra, array_flip(['courier', 'tracking_number']));

            match ($status) {
                'confirmed' => $fields['confirmed_at'] = now(),
                'shipped' => $fields['shipped_at'] = now(),
                'delivered' => $fields['delivered_at'] = now(),
                default => null,
            };

            // Confirming takes the reserved units off the shelf for good.
            if (in_array($status, ['confirmed', 'processing', 'shipped'], true) && ! $order->stock_committed) {
                $this->inventory->commit($order);
            }

            $order->update($fields);
            $this->history($order, $from, $status, $note, $user);

            // Cash on delivery is collected when the parcel is handed over.
            if ($status === 'delivered' && $order->payment_method === 'cod' && ! $order->isPaid()) {
                $payment = $order->payments()->where('method', 'cod')->first()
                    ?? Payment::create(['order_id' => $order->id, 'method' => 'cod', 'amount' => $order->balanceDue(), 'currency' => $order->currency]);
                $this->payments->complete($payment, 'COD-'.$order->number, ['source' => 'delivered'], $user);
            }

            ActivityLog::record('order.status', $order, ['from' => $from, 'to' => $status]);
        });

        $this->notify($order->fresh());

        return $order->fresh();
    }

    public function cancel(Order $order, string $reason, ?User $user = null, bool $notify = true): Order
    {
        if (! $order->canMoveTo('cancelled')) {
            throw new \RuntimeException('This order can no longer be cancelled. Use a return instead.');
        }

        DB::transaction(function () use ($order, $reason, $user) {
            $from = $order->status;
            $this->inventory->release($order, 'Cancelled');

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'payment_status' => $order->isPaid() ? $order->payment_status : ($order->payment_status === 'pending_verification' ? 'failed' : $order->payment_status),
            ]);

            $order->payments()->whereIn('status', ['initiated', 'pending_verification'])->update(['status' => 'cancelled']);

            // Give the coupon use back so the shopper can use it again.
            if ($order->coupon_code) {
                $usage = \App\Models\CouponUsage::where('order_id', $order->id)->first();

                if ($usage) {
                    \App\Models\Coupon::whereKey($usage->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                    $usage->delete();
                }
            }

            $this->history($order, $from, 'cancelled', $reason, $user);
            ActivityLog::record('order.cancelled', $order, ['reason' => $reason]);
        });

        if ($notify) {
            $this->notify($order->fresh());
        }

        return $order->fresh();
    }

    public function history(Order $order, ?string $from, string $to, ?string $note, ?User $user, bool $visible = true): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'is_customer_visible' => $visible,
            'user_id' => $user?->id,
        ]);
    }

    public function notify(Order $order): void
    {
        if ($order->customer_email) {
            rescue(fn () => \Illuminate\Support\Facades\Notification::route('mail', $order->customer_email)
                ->notify(new OrderStatusMail($order)), report: true);
        }
    }
}
