<?php

namespace App\Services;

/** Thrown when an order asks for more than can be sold. The message is safe to show shoppers. */
class OutOfStockException extends \RuntimeException
{
    public static function for(string $label, int $available): self
    {
        return new self($available > 0
            ? "Only {$available} left of {$label}. Please lower the quantity."
            : "{$label} has just sold out. Please remove it from your cart.");
    }
}
