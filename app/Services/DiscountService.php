<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;

/**
 * Coupon rules. Works on a Quote that already has its lines and subtotal and fills in
 * the discount, how it is spread over the lines, and free shipping.
 */
class DiscountService
{
    public function find(string $code): ?Coupon
    {
        return Coupon::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])->with('products:id', 'categories:id')->first();
    }

    /** Returns null when the coupon can be used, otherwise a reason the shopper can read. */
    public function check(Coupon $coupon, Quote $quote, ?string $phone): ?string
    {
        $now = now();

        if (! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isAfter($now))) {
            return 'This code is not active.';
        }

        if ($coupon->ends_at && $coupon->ends_at->isBefore($now)) {
            return 'This code has expired.';
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return 'This code has been fully used.';
        }

        if ($coupon->min_order_amount !== null && $quote->subtotal < \App\Support\Money::toMinor($coupon->min_order_amount)) {
            return 'Spend at least '.\App\Support\Money::format($coupon->min_order_amount).' to use this code.';
        }

        if (! collect($quote->lines)->contains(fn ($line) => $this->lineEligible($coupon, $line))) {
            return 'This code does not apply to the items in your cart.';
        }

        if ($phone) {
            if ($coupon->first_order_only && Order::where('customer_phone', $phone)->whereNotIn('status', ['cancelled'])->exists()) {
                return 'This code is for first orders only.';
            }

            if ($coupon->usage_limit_per_customer !== null
                && $coupon->usages()->where('customer_phone', $phone)->count() >= $coupon->usage_limit_per_customer) {
                return 'You have already used this code.';
            }
        }

        return null;
    }

    /** Apply the coupon to the quote: total discount spread over eligible lines, last line takes the rounding. */
    public function apply(Coupon $coupon, Quote $quote): void
    {
        $eligibleKeys = [];

        foreach ($quote->lines as $key => $line) {
            $quote->lines[$key]['eligible'] = $this->lineEligible($coupon, $line);

            if ($quote->lines[$key]['eligible']) {
                $eligibleKeys[] = $key;
            }
        }

        $eligibleSubtotal = array_sum(array_map(fn ($key) => $quote->lines[$key]['subtotal'], $eligibleKeys));

        $discount = match ($coupon->type) {
            'percent' => (int) round($eligibleSubtotal * (float) $coupon->value / 100),
            'fixed' => min(\App\Support\Money::toMinor($coupon->value), $eligibleSubtotal),
            default => 0,
        };

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, \App\Support\Money::toMinor($coupon->max_discount_amount));
        }

        $quote->coupon = $coupon;
        $quote->discount = $discount;
        $quote->freeShipping = $coupon->type === 'free_shipping';

        $left = $discount;

        foreach ($eligibleKeys as $index => $key) {
            $share = $index === array_key_last($eligibleKeys)
                ? $left
                : (int) floor($discount * $quote->lines[$key]['subtotal'] / max(1, $eligibleSubtotal));

            $quote->lines[$key]['discount'] = $share;
            $left -= $share;
        }
    }

    private function lineEligible(Coupon $coupon, array $line): bool
    {
        $product = $line['variant']->product;

        return match ($coupon->applies_to) {
            'products' => $coupon->products->contains('id', $product->id),
            'categories' => $product->category_id && $coupon->categories->contains('id', $product->category_id),
            default => true,
        };
    }
}
