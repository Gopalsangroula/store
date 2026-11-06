<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'token', 'customer_id', 'coupon_code', 'contact_phone', 'converted_at'];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
