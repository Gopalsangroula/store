<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The shopper's cart, kept in the database and found by a random token in a cookie
 * (one cookie per store domain), so it survives closing the browser.
 */
class CartService
{
    public const COOKIE = 'cart_token';

    public const MAX_QUANTITY = 20;

    private ?Cart $cart = null;

    public function current(Request $request, bool $create = false): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        // The long-lived cookie survives closing the browser; the session copy covers browsers
        // that drop or block the cookie.
        $token = (string) ($request->cookie(self::COOKIE) ?: ($request->hasSession() ? $request->session()->get(self::COOKIE) : ''));

        if ($token !== '') {
            $this->cart = Cart::where('token', $token)->whereNull('converted_at')->with('items')->first();
        }

        if (! $this->cart && $create) {
            $this->cart = Cart::create([
                'token' => Str::random(48),
                'customer_id' => auth('customer')->id(),
            ]);
            $this->cart->setRelation('items', collect());

            if ($request->hasSession()) {
                $request->session()->put(self::COOKIE, $this->cart->token);
            }

            Cookie::queue(Cookie::make(self::COOKIE, $this->cart->token, 60 * 24 * 30, null, null, null, true, false, 'lax'));
        }

        return $this->cart;
    }

    public function count(Request $request): int
    {
        return $this->current($request)?->items->sum('quantity') ?? 0;
    }

    public function add(Request $request, ProductVariant $variant, int $quantity): Cart
    {
        $cart = $this->current($request, true);
        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id]);
        $this->setQuantity($item, $variant, ($item->exists ? $item->quantity : 0) + $quantity);

        return $cart->load('items');
    }

    public function update(Request $request, int $itemId, int $quantity): void
    {
        $cart = $this->current($request);
        $item = $cart?->items()->with('variant')->find($itemId);

        if (! $item) {
            return;
        }

        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        $this->setQuantity($item, $item->variant, $quantity);
    }

    public function remove(Request $request, int $itemId): void
    {
        $this->current($request)?->items()->whereKey($itemId)->delete();
    }

    public function forget(): void
    {
        $this->cart = null;
        session()->forget(self::COOKIE);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    private function setQuantity(CartItem $item, ProductVariant $variant, int $quantity): void
    {
        $quantity = max(1, min(self::MAX_QUANTITY, $quantity));

        if ($variant->track_inventory && ! $variant->allow_preorder) {
            $available = $variant->available();

            if ($available <= 0) {
                throw OutOfStockException::for($variant->label(), 0);
            }

            $quantity = min($quantity, $available);
        }

        $item->quantity = $quantity;
        $item->save();
    }
}
