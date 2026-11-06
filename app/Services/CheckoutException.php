<?php

namespace App\Services;

/** A checkout problem the shopper can fix (bad coupon, empty cart, unavailable payment method). */
class CheckoutException extends \RuntimeException
{
}
