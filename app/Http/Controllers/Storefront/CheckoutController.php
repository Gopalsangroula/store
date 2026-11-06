<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Services\CartService;
use App\Services\CheckoutException;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\OutOfStockException;
use App\Services\PricingService;
use App\Support\Money;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public const PROVINCES = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim'];

    public function __construct(private CartService $carts, private PricingService $pricing)
    {
    }

    public function show(Request $request)
    {
        $cart = $this->carts->current($request);

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        $customer = auth('customer')->user();
        $address = $customer?->addresses()->orderByDesc('is_default')->first();
        $city = old('city', $address?->city);

        return view('storefront.checkout', [
            'cart' => $cart,
            'quote' => $this->pricing->quote($cart, $city ?: 'Kathmandu', null, $customer?->phone),
            'methods' => $this->methods(),
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort')->get(),
            'cities' => $this->cities(),
            'provinces' => self::PROVINCES,
            'customer' => $customer,
            'address' => $address,
        ]);
    }

    /** Live totals while the shopper fills the form (city changes shipping, coupon changes discount). */
    public function quote(Request $request)
    {
        $cart = $this->carts->current($request);

        if (! $cart) {
            return response()->json(['ok' => false], 404);
        }

        $quote = $this->pricing->quote($cart, (string) $request->input('city'), $request->input('coupon_code') ?: null, $request->input('phone') ?: null);
        $format = fn (int $minor) => Money::format(Money::fromMinor($minor), $this->currentStore()->base_currency);

        return response()->json([
            'ok' => true,
            'subtotal' => $format($quote->subtotal),
            'discount' => $quote->discount > 0 ? '- '.$format($quote->discount) : null,
            'shipping' => $quote->zone ? ($quote->shipping === 0 ? 'Free' : $format($quote->shipping)) : 'Enter your city',
            'zone' => $quote->zone?->name.($quote->zone?->estimated_days ? ' · '.$quote->zone->estimated_days : ''),
            'tax' => $quote->tax > 0 ? $format($quote->tax) : null,
            'tax_label' => $quote->taxRate ? $quote->taxRate->name.($quote->taxInclusive ? ' (included)' : '') : null,
            'total' => $format($quote->total),
            'coupon' => $quote->coupon?->code,
            'coupon_error' => $quote->couponError,
            'cod' => $quote->zone?->cod_available ?? true,
        ]);
    }

    public function place(Request $request, CheckoutService $checkout, OrderService $orders)
    {
        $cart = $this->carts->current($request);

        if (! $cart) {
            return redirect()->route('shop.cart');
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'regex:/^(\+?977[- ]?)?9[678]\d{8}$/'],
            'email' => 'nullable|email|max:150',
            'province' => 'nullable|string|in:'.implode(',', self::PROVINCES),
            'city' => 'required|string|max:100',
            'area' => 'nullable|string|max:150',
            'address' => 'required|string|max:250',
            'landmark' => 'nullable|string|max:250',
            'note' => 'nullable|string|max:1000',
            'payment_method' => 'required|string|in:'.implode(',', array_keys(PaymentMethod::CODES)),
            'coupon_code' => 'nullable|string|max:50',
        ], [
            'phone.regex' => 'Enter a 10-digit Nepali mobile number, e.g. 98XXXXXXXX.',
        ]);

        $data['phone'] = substr(preg_replace('/\D/', '', $data['phone']), -10);
        $data['coupon_code'] = ($data['coupon_code'] ?? null) ?: $cart->coupon_code;

        try {
            $order = $checkout->placeOrder($cart, $data, $request->ip());
        } catch (CheckoutException|OutOfStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->carts->forget();

        // Remember the order on this device so the shopper can come back to it.
        $request->session()->push('orders', $order->number);
        $orders->notify($order);

        $url = route('shop.order', ['number' => $order->number, 't' => $order->access_token]);

        return $order->payment_method === 'cod'
            ? redirect($url)->with('placed', true)
            : redirect()->route('shop.order.pay', ['number' => $order->number, 't' => $order->access_token]);
    }

    private function methods()
    {
        $store = $this->currentStore();

        return PaymentMethod::where('is_enabled', true)->orderBy('sort')->get()
            ->filter(fn (PaymentMethod $method) => $method->isReady()
                && ($method->code !== 'khalti' || $store->hasFeature('khalti'))
                && ($method->code !== 'qr' || $store->hasFeature('qr')))
            ->values();
    }

    private function cities(): array
    {
        return ShippingZone::where('is_active', true)->get()
            ->flatMap(fn ($zone) => array_map('trim', explode(',', (string) $zone->cities)))
            ->filter()->unique()->sort()->values()->all();
    }
}
