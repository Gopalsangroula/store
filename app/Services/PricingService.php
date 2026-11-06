<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use App\Support\Money;

/**
 * Turns a cart into a Quote: line prices, coupon, shipping and tax, in that order.
 *
 * Tax rules (Nepal VAT by default, 13% inclusive):
 *  - The taxable value of a line is its price after its share of the discount.
 *  - Inclusive: tax = value x r / (100 + r) and is already inside the total.
 *  - Exclusive: tax = value x r / 100 and is added to the total.
 *  - Shipping is taxed only when the rate says so. Each line is rounded to the paisa.
 */
class PricingService
{
    public function __construct(private DiscountService $discounts)
    {
    }

    public function quote(Cart $cart, ?string $city = null, ?string $couponCode = null, ?string $phone = null): Quote
    {
        $quote = new Quote;
        $cart->loadMissing('items.variant.product', 'items.variant.inventory');

        foreach ($cart->items as $item) {
            $variant = $item->variant;

            if (! $variant || ! $variant->is_active || $variant->product?->status !== 'active') {
                continue;
            }

            $unit = Money::toMinor($variant->price);
            $quote->lines[] = [
                'item_id' => $item->id,
                'variant' => $variant,
                'quantity' => $item->quantity,
                'unit' => $unit,
                'subtotal' => $unit * $item->quantity,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'eligible' => false,
            ];
        }

        $quote->subtotal = array_sum(array_column($quote->lines, 'subtotal'));

        $code = $couponCode ?? $cart->coupon_code;

        if ($code && ! $quote->isEmpty()) {
            $coupon = $this->discounts->find($code);
            $error = $coupon ? $this->discounts->check($coupon, $quote, $phone) : 'This code is not valid.';

            if ($error) {
                $quote->couponError = $error;
            } else {
                $this->discounts->apply($coupon, $quote);
            }
        }

        $this->shipping($quote, $city);
        $this->tax($quote);

        $quote->total = $quote->subtotal - $quote->discount + $quote->shipping + ($quote->taxInclusive ? 0 : $quote->tax);

        return $quote;
    }

    public function zoneFor(?string $city): ?ShippingZone
    {
        $zones = ShippingZone::where('is_active', true)->orderBy('sort')->orderBy('id')->get();

        if ($city !== null && trim($city) !== '') {
            // A zone that names the city wins over a catch-all zone.
            return $zones->first(fn (ShippingZone $zone) => $zone->cityList() !== [] && $zone->covers($city))
                ?? $zones->first(fn (ShippingZone $zone) => $zone->cityList() === []);
        }

        return null;
    }

    private function shipping(Quote $quote, ?string $city): void
    {
        $quote->zone = $this->zoneFor($city);

        if (! $quote->zone || $quote->isEmpty() || $quote->freeShipping) {
            return;
        }

        $afterDiscount = $quote->subtotal - $quote->discount;
        $freeOver = $quote->zone->free_over !== null ? Money::toMinor($quote->zone->free_over) : null;

        $quote->shipping = $freeOver !== null && $afterDiscount >= $freeOver ? 0 : Money::toMinor($quote->zone->rate);
    }

    private function tax(Quote $quote): void
    {
        $rate = TaxRate::where('is_active', true)->first();
        $quote->taxRate = $rate;

        if (! $rate) {
            foreach ($quote->lines as $key => $line) {
                $quote->lines[$key]['total'] = $line['subtotal'] - $line['discount'];
            }

            return;
        }

        $quote->taxInclusive = $rate->is_inclusive;
        $r = (float) $rate->rate;
        $taxOf = fn (int $value) => (int) round($rate->is_inclusive ? $value * $r / (100 + $r) : $value * $r / 100);

        $tax = 0;

        foreach ($quote->lines as $key => $line) {
            $value = $line['subtotal'] - $line['discount'];
            $lineTax = $line['variant']->product->is_taxable ? $taxOf($value) : 0;
            $quote->lines[$key]['tax'] = $lineTax;
            $quote->lines[$key]['total'] = $value + ($rate->is_inclusive ? 0 : $lineTax);
            $tax += $lineTax;
        }

        if ($rate->applies_to_shipping) {
            $tax += $taxOf($quote->shipping);
        }

        $quote->tax = $tax;
    }
}
