<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\InventoryService;
use App\Services\StoreProvisioner;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Permission::ALL as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::create(['slug' => $slug, 'name' => $name, 'group' => $group]);
            }
        }

        Plan::create(['name' => 'All', 'slug' => 'all', 'price_monthly' => 1000, 'price_yearly' => 10000, 'features' => null]);
    }

    /** A ready store reachable at http://{slug}.store.test with an owner account. */
    protected function makeStore(string $slug = 'alpha'): Store
    {
        return app(StoreProvisioner::class)->create([
            'name' => ucfirst($slug).' Shop',
            'slug' => $slug,
            'owner_name' => ucfirst($slug).' Owner',
            'owner_email' => $slug.'@example.test',
            'owner_password' => 'password-123',
            'plan_id' => Plan::value('id'),
            'trial_days' => 14,
        ]);
    }

    protected function makeProduct(Store $store, float $price = 1130, int $stock = 10, bool $preorder = false, string $sku = 'SKU-1'): ProductVariant
    {
        return app(TenantContext::class)->run($store, function () use ($price, $stock, $preorder, $sku) {
            $product = Product::create(['name' => 'Test Product '.$sku, 'slug' => 'test-'.strtolower($sku), 'status' => 'active']);
            $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Black', 'sku' => $sku, 'price' => $price, 'cost_price' => $price / 2, 'allow_preorder' => $preorder]);

            if ($stock > 0) {
                $inventory = app(InventoryService::class);
                $inventory->adjust($variant, $inventory->defaultWarehouse(), $stock, 'Opening stock');
            }

            return $variant->fresh();
        });
    }

    protected function url(Store $store, string $path = '/'): string
    {
        return 'http://'.$store->slug.'.store.test/'.ltrim($path, '/');
    }

    /** Checkout form for a Kathmandu address. */
    protected function checkoutData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sita Sharma',
            'phone' => '9841000001',
            'email' => 'sita@example.test',
            'city' => 'Kathmandu',
            'address' => 'House 12, Baneshwor',
            'payment_method' => 'cod',
        ], $overrides);
    }

    protected function tenant(Store $store, callable $callback): mixed
    {
        return app(TenantContext::class)->run($store, $callback);
    }
}
