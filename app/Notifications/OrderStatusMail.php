<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Order placed / status changed email to the shopper, sent from the store's own name. */
class OrderStatusMail extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items', 'store');
        $store = $order->store;
        $url = $store->url('/order/'.$order->number.'?t='.$order->access_token);

        $message = (new MailMessage)
            ->from(config('mail.from.address'), $store->name)
            ->subject("Order {$order->number}: {$order->statusLabel()}")
            ->greeting('Hi '.$order->customer_name.',');

        $message->line(match ($order->status) {
            'pending' => 'Thank you for your order. We have received it and will confirm it shortly.',
            'confirmed' => 'Your order is confirmed and will be packed soon.',
            'processing' => 'We are packing your order.',
            'shipped' => 'Your order is on its way'.($order->courier ? ' with '.$order->courier : '').($order->tracking_number ? ' (tracking '.$order->tracking_number.')' : '').'.',
            'delivered' => 'Your order has been delivered. Thank you for shopping with us.',
            'cancelled' => 'Your order has been cancelled.',
            default => 'Your order status is now: '.$order->statusLabel().'.',
        });

        foreach ($order->items as $item) {
            $message->line('- '.$item->quantity.' x '.$item->product_name.($item->variant_name ? ' ('.$item->variant_name.')' : ''));
        }

        return $message
            ->line('Total: '.$order->money($order->grand_total).' ('.$order->paymentStatusLabel().')')
            ->action('View your order', $url)
            ->salutation('- '.$store->name);
    }
}
