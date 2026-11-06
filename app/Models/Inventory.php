<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Current stock of one variant in one warehouse. Only InventoryService changes it. */
class Inventory extends Model
{
    use BelongsToStore;

    protected $table = 'inventory';

    protected $fillable = ['store_id', 'warehouse_id', 'variant_id', 'on_hand', 'reserved', 'low_stock_threshold'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }
}
