<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use BelongsToStore;

    public const TYPES = [
        'percent' => 'Percentage off',
        'fixed' => 'Fixed amount off',
        'free_shipping' => 'Free shipping',
    ];

    protected $fillable = [
        'store_id', 'code', 'name', 'type', 'value', 'min_order_amount', 'max_discount_amount', 'applies_to',
        'first_order_only', 'usage_limit', 'usage_limit_per_customer', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function describe(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off',
            'fixed' => \App\Support\Money::format($this->value).' off',
            default => 'Free shipping',
        };
    }
}
