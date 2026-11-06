<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const FEATURES = [
        'inventory' => 'Warehouses, purchase orders and stock ledger',
        'coupons' => 'Coupons and discounts',
        'khalti' => 'Khalti online payment',
        'qr' => 'Direct QR payment',
        'accounting' => 'Invoices, expenses and accounting',
        'reports' => 'Advanced reports',
        'multi_currency' => 'Multi-currency display',
        'custom_domain' => 'Custom domain',
        'api' => 'API access',
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'setup_fee', 'max_products',
        'max_staff', 'max_orders_per_month', 'features', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_active' => 'boolean'];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
