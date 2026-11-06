<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

/** One store must never see or change another store's data, whatever the URL says. */
class TenantIsolationTest extends TestCase
{
    public function test_storefront_only_shows_its_own_products(): void
    {
        $a = $this->makeStore('alpha');
        $b = $this->makeStore('beta');
        $this->makeProduct($a, sku: 'A-1');
        $this->makeProduct($b, sku: 'B-1');

        $this->get($this->url($a, '/shop'))->assertOk()->assertSee('Test Product A-1')->assertDontSee('Test Product B-1');
        $this->get($this->url($a, '/product/test-b-1'))->assertNotFound();
    }

    public function test_unknown_host_is_not_found(): void
    {
        $this->makeStore('alpha');

        $this->get('http://nobody.store.test/')->assertNotFound();
        $this->get('http://evil.example.com/')->assertNotFound();
    }

    public function test_staff_cannot_open_another_stores_order_by_id(): void
    {
        $a = $this->makeStore('alpha');
        $b = $this->makeStore('beta');
        $variant = $this->makeProduct($b, sku: 'B-1');

        $this->post($this->url($b, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($b, '/checkout'), $this->checkoutData());
        $orderOfB = Order::withoutGlobalScopes()->where('store_id', $b->id)->firstOrFail();

        $ownerOfA = User::where('email', 'alpha@example.test')->first();

        // Alpha's owner, on alpha's admin, asking for beta's order id.
        $this->actingAs($ownerOfA)->get($this->url($a, '/admin/orders/'.$orderOfB->id))->assertNotFound();
        // Alpha's owner going to beta's admin directly.
        $this->actingAs($ownerOfA)->get($this->url($b, '/admin'))->assertForbidden();
    }

    public function test_staff_of_one_store_cannot_sign_in_to_another(): void
    {
        $a = $this->makeStore('alpha');
        $b = $this->makeStore('beta');

        $this->post($this->url($b, '/admin/login'), ['email' => 'alpha@example.test', 'password' => 'password-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->post($this->url($a, '/admin/login'), ['email' => 'alpha@example.test', 'password' => 'password-123'])
            ->assertRedirect($this->url($a, '/admin'));
    }

    public function test_cart_cannot_add_another_stores_variant(): void
    {
        $a = $this->makeStore('alpha');
        $b = $this->makeStore('beta');
        $variantOfB = $this->makeProduct($b, sku: 'B-1');

        $this->postJson($this->url($a, '/cart/add'), ['variant_id' => $variantOfB->id])->assertNotFound();
    }

    public function test_new_records_are_stamped_with_the_current_store(): void
    {
        $a = $this->makeStore('alpha');
        $product = $this->tenant($a, fn () => Product::create(['name' => 'X', 'slug' => 'x']));

        $this->assertSame($a->id, $product->store_id);
    }
}
