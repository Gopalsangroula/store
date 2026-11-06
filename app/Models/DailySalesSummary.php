<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/** One row per store per day, rebuilt by ReportService so dashboards never scan every order. */
class DailySalesSummary extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id', 'date', 'orders_count', 'items_sold', 'gross_sales', 'discounts', 'shipping', 'tax',
        'net_sales', 'cost_of_goods', 'new_customers',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
