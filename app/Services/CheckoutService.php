<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Places an order from a cart in one database transaction: re-price everything on the
 * server, lock and reserve stock, record the coupon, snapshot the lines, and open a
 * payment. If any step fails nothing is saved.
 */
class CheckoutService
{
    public function __construct(
        private PricingService $pricing,
        private InventoryService $inventory,
        private DocumentNumber $numbers,
        private TenantContext $tenant,
    ) {
    }

    /**
     * @param  array{name: string, phone: string, email?: ?string, province?: ?string, city: string, area?: ?string, address: string, landmark?: ?string, note?: ?string, payment_method: string, coupon_code?: ?string}  $data
     */
    public function placeOrder(Cart $cart, array $data, ?string $ip = null): Order
    {
        $store = $this->tenant->store();

        $method = PaymentMethod::where('code', $data['payment_method'])->where('is_enabled', true)->first();

        if (! $method || ! $method->isReady()) {
            throw new CheckoutException('Please choose another payment method.');
        }

        return DB::transaction(function () use ($cart, $data, $ip, $store, $method) {
            // Re-read the cart inside the transaction so a double click cannot order it twice.
            $cart = Cart::whereKey($cart->id)->whereNull('converted_at')->lockForUpdate()->first();

            if (! $cart) {
                throw new CheckoutException('This cart has already been ordered.');
            }

            $quote = $this->pricing->quote($cart, $data['city'], $data['coupon_code'] ?? $cart->coupon_code, $data['phone']);

            if ($quote->isEmpty()) {
                throw new CheckoutException('Your cart is empty.');
            }

            if ($quote->couponError && ($data['coupon_code'] ?? $cart->coupon_code)) {
                throw new CheckoutException($quote->couponError);
            }

            if (! $quote->zone) {
                throw new CheckoutException('We do not deliver to that city yet.');
            }

            if ($method->code === 'cod' && ! $quote->zone->cod_available) {
                throw new CheckoutException('Cash on delivery is not available in '.$quote->zone->name.'. Please pay online.');
            }

            $customer = $this->customer($data);

            $order = Order::create([
                'number' => $this->numbers->next($store, 'order'),
                'access_token' => Str::random(40),
                'customer_id' => $customer->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $method->code,
                'currency' => $store->base_currency,
                'base_currency' => $store->base_currency,
                'exchange_rate' => 1,
                'subtotal' => $quote->money('subtotal'),
                'discount_total' => $quote->money('discount'),
                'shipping_total' => $quote->money('shipping'),
                'tax_total' => $quote->money('tax'),
                'tax_inclusive' => $quote->taxInclusive,
                'grand_total' => $quote->money('total'),
                'coupon_code' => $quote->coupon?->code,
                'customer_name' => $data['name'],
                'customer_email' => $data['email'] ?? null,
                'customer_phone' => $data['phone'],
                'shipping_province' => $data['province'] ?? null,
                'shipping_city' => $data['city'],
                'shipping_area' => $data['area'] ?? null,
                'shipping_address' => $data['address'],
                'shipping_landmark' => $data['landmark'] ?? null,
                'shipping_zone_id' => $quote->zone->id,
                'shipping_zone_name' => $quote->zone->name,
                'customer_note' => $data['note'] ?? null,
                'ip' => $ip,
                'placed_at' => now(),
            ]);

            $reserveLines = [];

            foreach ($quote->lines as $line) {
                $variant = $line['variant'];
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $variant->image_path ?? $variant->product->images()->value('path'),
                    'unit_price' => Money::fromMinor($line['unit']),
                    'unit_cost' => $variant->cost_price,
                    'quantity' => $line['quantity'],
                    'discount_amount' => Money::fromMinor($line['discount']),
                    'tax_amount' => Money::fromMinor($line['tax']),
                    'line_total' => Money::fromMinor($line['total']),
                ]);
                $reserveLines[$item->id] = ['variant' => $variant, 'quantity' => $line['quantity']];
            }

            // Online payments hold stock for a limited time; COD holds it until the shop acts.
            $expiresAt = $method->code === 'cod' ? null : now()->addMinutes((int) config('platform.reservation_minutes'));
            $preorders = $this->inventory->reserveForOrder($order, $reserveLines, $expiresAt);

            foreach (array_keys(array_filter($preorders)) as $itemId) {
                OrderItem::whereKey($itemId)->update(['is_preorder' => true]);
            }

            if ($quote->coupon) {
                $this->recordCoupon($quote->coupon, $order, $customer, $quote->discount);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'to_status' => 'pending',
                'note' => 'Order placed with '.$method->name.'.',
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => $method->code,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'status' => 'initiated',
            ]);

            $customer->increment('orders_count');
            $customer->update(['last_order_at' => now()]);

            $cart->update(['converted_at' => now(), 'customer_id' => $customer->id, 'contact_phone' => $data['phone']]);

            return $order;
        });
    }

    private function customer(array $data): Customer
    {
        $signedIn = auth('customer')->user();

        if ($signedIn) {
            return $signedIn;
        }

        $customer = Customer::where('phone', $data['phone'])->lockForUpdate()->first();

        if ($customer) {
            // A guest must not be able to rename someone else's account; only fill gaps.
            if (! $customer->email && ! empty($data['email'])) {
                $customer->update(['email' => $data['email']]);
            }

            return $customer;
        }

        return Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ]);
    }

    private function recordCoupon(Coupon $coupon, Order $order, Customer $customer, int $discount): void
    {
        // Re-check the global limit under a lock so the last use cannot be taken twice.
        $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();

        if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
            throw new CheckoutException('This code has just been fully used.');
        }

        $locked->increment('used_count');

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'customer_phone' => $order->customer_phone,
            'discount_amount' => Money::fromMinor($discount),
        ]);
    }
}
