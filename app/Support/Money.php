<?php

namespace App\Support;

/**
 * Money is kept in the database as DECIMAL(19,4) and calculated here in whole paisa
 * (integers), so sums never pick up floating point dust.
 */
final class Money
{
    public static function toMinor(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    public static function format(string|int|float|null $amount, string $currency = 'NPR', ?float $rate = null): string
    {
        $value = (float) $amount * ($rate ?? 1);
        $symbol = match ($currency) {
            'NPR' => 'Rs.',
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            default => $currency,
        };

        $decimals = fmod(round($value, 2), 1.0) == 0.0 ? 0 : 2;

        return $symbol.($symbol === 'Rs.' ? ' ' : '').number_format($value, $decimals);
    }
}
