<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Support\Money;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_cash_on_delivery_order_reserves_stock_and_prices_vat_inclusive(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, price: 1130, stock: 5);

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData())->assertRedirect();

        $order = $this->tenant($store, fn () => Order::with('items')->firstOrFail());

        // 2 x 1130 = 2260, inside the valley delivery 100, VAT 13% included: 2260 x 13/113 = 260.
        $this->assertSame('2260.0000', $order->subtotal);
        $this->assertSame('100.0000', $order->shipping_total);
        $this->assertSame('260.0000', $order->tax_total);
        $this->assertSame('2360.0000', $order->grand_total);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-000001$/', $order->number);

        $row = $this->tenant($store, fn () => Inventory::where('variant_id', $variant->id)->first());
        $this->assertSame(5, $row->on_hand);
        $this->assertSame(2, $row->reserved);
    }

    public function test_delivering_a_cod_order_collects_cash_issues_invoice_and_balances_books(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, price: 1130, stock: 5);
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData());

        $this->tenant($store, function () use ($variant) {
            $order = Order::firstOrFail();
            $orders = app(OrderService::class);
            $orders->changeStatus($order, 'confirmed');
            $orders->changeStatus($order->fresh(), 'shipped');
            $orders->changeStatus($order->fresh(), 'delivered');

            $order->refresh();
            $this->assertSame('paid', $order->payment_status);
            $this->assertSame($order->grand_total, $order->paid_total);
            $this->assertTrue($order->stock_committed);

            $invoice = Invoice::where('order_id', $order->id)->firstOrFail();
            $this->assertMatchesRegularExpression('/^INV-\d{4}-000001$/', $invoice->number);

            $row = Inventory::where('variant_id', $variant->id)->first();
            $this->assertSame(4, $row->on_hand);
            $this->assertSame(0, $row->reserved);

            $this->assertSame(
                Money::toMinor(JournalEntryLine::sum('debit')),
                Money::toMinor(JournalEntryLine::sum('credit')),
                'Every journal entry must balance.'
            );
        });
    }

    public function test_cancelling_releases_reserved_stock(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 3);
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id, 'quantity' => 3]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData());

        $this->tenant($store, function () use ($variant) {
            app(OrderService::class)->cancel(Order::firstOrFail(), 'Test');
            $row = Inventory::where('variant_id', $variant->id)->first();
            $this->assertSame(3, $row->on_hand);
            $this->assertSame(0, $row->reserved);
        });
    }

    public function test_cannot_buy_more_than_is_available(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 1);

        // First shopper takes the last unit.
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData());

        // Second shopper (fresh cookies) cannot even add it now.
        $this->flushSession();
        $this->withCookies([])->postJson($this->url($store, '/cart/add'), ['variant_id' => $variant->id])
            ->assertStatus(422)->assertJsonPath('ok', false);

        $this->assertSame(1, $this->tenant($store, fn () => Order::count()));
    }

    public function test_stock_is_rechecked_at_checkout_not_only_in_the_cart(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 2);

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id, 'quantity' => 2]);

        // Meanwhile the shop sells both units in person.
        $this->tenant($store, function () use ($variant) {
            $inventory = app(\App\Services\InventoryService::class);
            $inventory->adjust($variant, $inventory->defaultWarehouse(), -2, 'Sold in shop');
        });

        $this->post($this->url($store, '/checkout'), $this->checkoutData())->assertSessionHas('error');
        $this->assertSame(0, $this->tenant($store, fn () => Order::count()));
    }

    public function test_preorder_variant_can_be_ordered_without_stock(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store, stock: 0, preorder: true);

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData())->assertRedirect();

        $this->assertTrue($this->tenant($store, fn () => Order::with('items')->firstOrFail()->items->first()->is_preorder));
    }

    public function test_guest_cannot_open_someone_elses_order_without_the_secret_link(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store);
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData());
        $order = $this->tenant($store, fn () => Order::firstOrFail());

        $this->flushSession();
        $this->get($this->url($store, '/order/'.$order->number))->assertNotFound();
        $this->get($this->url($store, '/order/'.$order->number.'?t=wrong'))->assertNotFound();
        $this->get($this->url($store, '/order/'.$order->number.'?t='.$order->access_token))->assertOk()->assertSee($order->number);
    }

    public function test_invoice_numbers_are_sequential_per_store(): void
    {
        $a = $this->makeStore('alpha');
        $b = $this->makeStore('beta');

        $numbers = app(\App\Services\DocumentNumber::class);
        $year = now()->year;

        $this->assertSame("INV-{$year}-000001", $numbers->next($a, 'invoice'));
        $this->assertSame("INV-{$year}-000002", $numbers->next($a, 'invoice'));
        $this->assertSame("INV-{$year}-000001", $numbers->next($b, 'invoice'));
    }

    public function test_store_owner_sees_dashboard(): void
    {
        $store = $this->makeStore();
        $owner = User::where('email', 'alpha@example.test')->first();

        $this->actingAs($owner)->get($this->url($store, '/admin'))->assertOk()->assertSee('Payments to verify');
    }
}
