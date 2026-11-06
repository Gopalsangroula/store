<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private function khaltiStore()
    {
        $store = $this->makeStore();
        $this->tenant($store, fn () => PaymentMethod::where('code', 'khalti')->first()->update(['is_enabled' => true, 'config' => ['secret_key' => 'test_secret_key_x', 'live' => false]]));

        return $store;
    }

    private function placeKhaltiOrder($store): Order
    {
        $variant = $this->makeProduct($store, price: 1000, stock: 5);
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['payment_method' => 'khalti']))->assertRedirect();

        return $this->tenant($store, fn () => Order::firstOrFail());
    }

    public function test_khalti_is_only_marked_paid_after_server_lookup(): void
    {
        $store = $this->khaltiStore();
        $order = $this->placeKhaltiOrder($store);

        Http::fake([
            '*/epayment/initiate/' => Http::response(['pidx' => 'PIDX123', 'payment_url' => 'https://test-pay.khalti.com/?pidx=PIDX123'], 200),
            '*/epayment/lookup/' => Http::sequence()
                ->push(['pidx' => 'PIDX123', 'status' => 'Pending', 'total_amount' => 110000], 200)
                ->push(['pidx' => 'PIDX123', 'status' => 'Completed', 'total_amount' => 110000, 'transaction_id' => 'TXN9'], 200),
        ]);

        $this->post($this->url($store, '/order/'.$order->number.'/pay/khalti?t='.$order->access_token))
            ->assertRedirect('https://test-pay.khalti.com/?pidx=PIDX123');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'dev.khalti.com/api/v2/epayment/initiate/')
            && $request->header('Authorization')[0] === 'Key test_secret_key_x'
            && $request['amount'] === 110000);

        // The shopper comes back claiming success, but Khalti still says Pending: not paid.
        $this->get($this->url($store, '/payment/khalti/return?pidx=PIDX123&status=Completed&transaction_id=FAKE'));
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Second return: Khalti confirms. Paid, confirmed and invoiced.
        $this->get($this->url($store, '/payment/khalti/return?pidx=PIDX123'));
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
    }

    public function test_repeated_khalti_return_does_not_pay_twice(): void
    {
        $store = $this->khaltiStore();
        $order = $this->placeKhaltiOrder($store);

        Http::fake([
            '*/epayment/initiate/' => Http::response(['pidx' => 'PIDX9', 'payment_url' => 'https://pay.example/x'], 200),
            '*/epayment/lookup/' => Http::response(['pidx' => 'PIDX9', 'status' => 'Completed', 'total_amount' => 110000, 'transaction_id' => 'T1'], 200),
        ]);

        $this->post($this->url($store, '/order/'.$order->number.'/pay/khalti?t='.$order->access_token));
        $this->get($this->url($store, '/payment/khalti/return?pidx=PIDX9'));
        $this->get($this->url($store, '/payment/khalti/return?pidx=PIDX9'));

        $order->refresh();
        $this->assertSame('1100.0000', $order->paid_total);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('status', 'completed')->count());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
    }

    public function test_khalti_amount_mismatch_is_not_accepted(): void
    {
        $store = $this->khaltiStore();
        $order = $this->placeKhaltiOrder($store);

        Http::fake([
            '*/epayment/initiate/' => Http::response(['pidx' => 'PIDX7', 'payment_url' => 'https://pay.example/x'], 200),
            '*/epayment/lookup/' => Http::response(['pidx' => 'PIDX7', 'status' => 'Completed', 'total_amount' => 1000, 'transaction_id' => 'T2'], 200),
        ]);

        $this->post($this->url($store, '/order/'.$order->number.'/pay/khalti?t='.$order->access_token));
        $this->get($this->url($store, '/payment/khalti/return?pidx=PIDX7'));

        $this->assertNotSame('paid', $order->fresh()->payment_status);
    }

    public function test_unknown_pidx_is_ignored(): void
    {
        $store = $this->khaltiStore();
        $this->get($this->url($store, '/payment/khalti/return?pidx=NOPE&status=Completed'))
            ->assertRedirect()->assertSessionHas('error', 'We could not find that payment.');
        $this->assertSame(0, Payment::withoutGlobalScopes()->where('status', 'completed')->count());
    }

    public function test_qr_payment_waits_for_staff_verification(): void
    {
        Storage::fake('local');
        $store = $this->makeStore();
        $this->tenant($store, fn () => PaymentMethod::where('code', 'qr')->first()->update(['is_enabled' => true, 'config' => ['qr_image' => 'stores/1/payments/qr.png']]));
        $variant = $this->makeProduct($store, price: 500, stock: 5);

        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData(['payment_method' => 'qr']));
        $order = $this->tenant($store, fn () => Order::firstOrFail());

        $this->get($this->url($store, '/order/'.$order->number.'/pay?t='.$order->access_token))->assertOk()->assertSee('Scan to pay');

        $this->post($this->url($store, '/order/'.$order->number.'/pay/manual?t='.$order->access_token), [
            'reference' => 'FP123456',
            // A real 1x1 PNG, so the image rule's file-type check runs without needing GD.
            'proof' => UploadedFile::fake()->createWithContent('paid.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('pending_verification', $order->payment_status);
        $payment = Payment::withoutGlobalScopes()->where('order_id', $order->id)->first();
        Storage::disk('local')->assertExists($payment->proof_path);

        // Staff confirm the money arrived.
        $owner = User::where('email', 'alpha@example.test')->first();
        $this->actingAs($owner)->post($this->url($store, '/admin/payments/'.$payment->id.'/verify'), ['transaction_id' => 'FP123456'])->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_sales_staff_without_permission_cannot_verify(): void
    {
        $store = $this->makeStore();
        $variant = $this->makeProduct($store);
        $this->post($this->url($store, '/cart/add'), ['variant_id' => $variant->id]);
        $this->post($this->url($store, '/checkout'), $this->checkoutData());
        $payment = Payment::withoutGlobalScopes()->firstOrFail();

        $packer = User::create(['name' => 'Packer', 'email' => 'packer@example.test', 'password' => 'password-123']);
        $this->tenant($store, function () use ($packer, $store) {
            $role = \App\Models\Role::create(['store_id' => $store->id, 'name' => 'Packer', 'slug' => 'packer']);
            $role->permissions()->sync(\App\Models\Permission::whereIn('slug', ['orders.view'])->pluck('id'));
            \App\Models\StoreStaff::create(['user_id' => $packer->id, 'role_id' => $role->id]);
        });

        $this->actingAs($packer)->get($this->url($store, '/admin/orders'))->assertOk();
        $this->actingAs($packer)->post($this->url($store, '/admin/payments/'.$payment->id.'/verify'))->assertForbidden();
    }
}
