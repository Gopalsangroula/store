<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use App\Support\Money;

/**
 * The priced contents of a cart. All amounts are integers in minor units (paisa).
 *
 *   subtotal - discount + shipping (+ tax when prices exclude it) = total
 */
class Quote
{
    /** @var list<array{variant: ProductVariant, quantity: int, unit: int, subtotal: int, discount: int, tax: int, total: int, eligible: bool}> */
    public array $lines = [];

    public int $subtotal = 0;

    public int $discount = 0;

    public int $shipping = 0;

    public int $tax = 0;

    public int $total = 0;

    public ?Coupon $coupon = null;

    public ?string $couponError = null;

    public bool $freeShipping = false;

    public ?ShippingZone $zone = null;

    public ?TaxRate $taxRate = null;

    public bool $taxInclusive = true;

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function itemCount(): int
    {
        return array_sum(array_column($this->lines, 'quantity'));
    }

    public function money(string $field): string
    {
        return Money::fromMinor($this->{$field});
    }
}
