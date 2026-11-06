<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Services\OutOfStockException;
use App\Services\PricingService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $carts, private PricingService $pricing)
    {
    }

    public function show(Request $request)
    {
        $cart = $this->carts->current($request);
        $quote = $cart ? $this->pricing->quote($cart) : null;

        return view('storefront.cart', ['cart' => $cart, 'quote' => $quote]);
    }

    public function add(Request $request)
    {
        $variant = $this->variant($request);

        try {
            $this->carts->add($request, $variant, (int) $request->input('quantity', 1));
        } catch (OutOfStockException $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, $variant->label().' added to your cart.');
    }

    /** "Buy" on a product row: add it and go straight to checkout. */
    public function buyNow(Request $request)
    {
        $variant = $this->variant($request);

        try {
            $this->carts->add($request, $variant, (int) $request->input('quantity', 1));
        } catch (OutOfStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.checkout');
    }

    public function update(Request $request, int $item)
    {
        try {
            $this->carts->update($request, $item, (int) $request->input('quantity'));
        } catch (OutOfStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function remove(Request $request, int $item)
    {
        $this->carts->remove($request, $item);

        return back()->with('status', 'Item removed.');
    }

    public function coupon(Request $request, DiscountService $discounts)
    {
        $code = strtoupper(trim((string) $request->validate(['code' => 'required|string|max:50'])['code']));
        $cart = $this->carts->current($request);

        if (! $cart || $cart->items->isEmpty()) {
            return back()->with('error', 'Add something to your cart first.');
        }

        $quote = $this->pricing->quote($cart, null, $code, auth('customer')->user()?->phone);

        if ($quote->couponError) {
            return back()->with('error', $quote->couponError)->withInput();
        }

        $cart->update(['coupon_code' => $quote->coupon->code]);

        return back()->with('status', 'Code '.$quote->coupon->code.' applied: '.$quote->coupon->describe().'.');
    }

    public function removeCoupon(Request $request)
    {
        $this->carts->current($request)?->update(['coupon_code' => null]);

        return back();
    }

    private function variant(Request $request): ProductVariant
    {
        $id = (int) $request->validate(['variant_id' => 'required|integer', 'quantity' => 'nullable|integer|min:1|max:20'])['variant_id'];

        return ProductVariant::with('product')
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->visible())
            ->findOrFail($id);
    }

    private function respond(Request $request, bool $ok, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'count' => $this->carts->count($request)], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }
}
