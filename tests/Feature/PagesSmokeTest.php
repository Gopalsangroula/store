<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/** Opens every screen with the full demo data loaded. Any error page fails the test. */
class PagesSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Permission::query()->delete();
        \App\Models\Plan::query()->delete();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_page_opens(): void
    {
        $store = Store::where('slug', 'appharu')->firstOrFail();
        $base = 'http://localhost';
        $order = Order::withoutGlobalScopes()->where('store_id', $store->id)->where('payment_status', 'paid')->firstOrFail();
        $product = Product::withoutGlobalScopes()->where('store_id', $store->id)->firstOrFail();

        $shop = ['/', '/shop', '/shop?q=watch&sort=price_asc', '/category/audio', '/product/'.$product->slug, '/cart', '/track',
            '/about-us', '/return-policy', '/account/login', '/account/register', '/sitemap.xml', '/api/v1/products', '/api/v1/categories',
            '/order/'.$order->number.'?t='.$order->access_token];

        foreach ($shop as $path) {
            $this->get($base.$path)->assertOk();
        }

        $owner = User::where('email', 'owner@appharu.com')->first();
        $admin = ['', '/products', '/products/create', '/products/'.$product->id.'/edit', '/categories', '/brands', '/reviews',
            '/inventory', '/inventory?filter=low', '/inventory/movements', '/warehouses', '/purchase-orders', '/purchase-orders/create', '/suppliers',
            '/orders', '/orders?payment=pending_verification', '/orders/'.$order->id, '/orders/'.$order->id.'/invoice', '/orders/'.$order->id.'/slip',
            '/carts', '/customers', '/customers/'.$order->customer_id, '/coupons', '/coupons/create', '/banners', '/pages', '/pages/create',
            '/finance/invoices', '/finance/payments', '/finance/credit-notes', '/finance/expenses', '/finance/accounts', '/finance/journal',
            '/reports', '/reports/products', '/reports/customers', '/reports/inventory', '/reports/finance',
            '/settings', '/settings/storefront', '/settings/payments', '/settings/shipping', '/settings/tax', '/settings/currency', '/settings/domains',
            '/staff', '/billing'];

        foreach ($admin as $path) {
            $this->actingAs($owner)->get($base.'/admin'.$path)->assertOk();
        }

        $super = User::where('email', 'admin@appharu.com')->first();

        foreach (['', '/stores', '/stores/create', '/stores/'.$store->id, '/plans', '/plans/create', '/billing', '/activity', '/settings'] as $path) {
            $this->actingAs($super)->get($base.'/superadmin'.$path)->assertOk();
        }

        // The second demo store answers on its own host with its own products.
        $this->get('http://tea.localhost/')->assertOk()->assertSee('Ilam Green Tea')->assertDontSee('Wireless Earbuds');
    }
}
