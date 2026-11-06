<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use Tests\TestCase;

class CouponTest extends TestCase
{
    public function test_percent_coupon_respects_cap_and_is_snapshotted(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, price: 10000, stock: 5);
        $this->tenant($store, fn () => Coupon::create(['code' => 'TEN', 'name' => '10%', 'type' => 'percent', 'value' => 10, 'max_discount_amount' => 500]));

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'ten']))->assertRedirect();

        $order = $this->tenant($store, fn () => Order::firstOrFail());
        $this->assertSame('500.0000', $order->discount_total); // 10% of 10,000 capped at 500
        $this->assertSame('TEN', $order->coupon_code);

        // Editing the coupon later never changes the order.
        $this->tenant($store, fn () => Coupon::first()->update(['value' => 50]));
        $this->assertSame('500.0000', $order->fresh()->discount_total);
    }

    public function test_first_order_coupon_refused_for_returning_phone(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 10);
        $this->tenant($store, fn () => Coupon::create(['code' => 'FIRST', 'name' => 'First', 'type' => 'fixed', 'value' => 100, 'first_order_only' => true]));

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'FIRST']))->assertRedirect();

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'FIRST']))->assertSessionHas('error', 'This code is for first orders only.');

        $this->assertSame(1, $this->tenant($store, fn () => Order::count()));
    }

    public function test_usage_limit_is_enforced(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 10);
        $this->tenant($store, fn () => Coupon::create(['code' => 'ONCE', 'name' => 'Once', 'type' => 'fixed', 'value' => 50, 'usage_limit' => 1]));

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'ONCE']));

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'ONCE', 'phone' => '9851000002']))
            ->assertSessionHas('error', 'This code has been fully used.');
    }

    public function test_free_shipping_coupon_removes_delivery_fee(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, price: 500, stock: 10);
        $this->tenant($store, fn () => Coupon::create(['code' => 'SHIP', 'name' => 'Ship', 'type' => 'free_shipping']));

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['coupon_code' => 'SHIP', 'city' => 'Pokhara']));

        $order = $this->tenant($store, fn () => Order::firstOrFail());
        $this->assertSame('0.0000', $order->shipping_total);
        $this->assertSame('500.0000', $order->grand_total);
    }
}
