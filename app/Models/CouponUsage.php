<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use BelongsToStore;

    public const UPDATED_AT = null;

    protected $fillable = ['store_id', 'coupon_id', 'order_id', 'customer_id', 'customer_phone', 'discount_amount'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
