<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One stock movement. Append-only: rows are never updated or deleted. */
class InventoryTransaction extends Model
{
    use BelongsToStore;

    public const UPDATED_AT = null;

    public const TYPES = [
        'purchase_receipt' => 'Goods received',
        'sale' => 'Sold',
        'adjustment' => 'Adjustment',
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'return' => 'Customer return',
        'cancellation' => 'Order cancelled',
    ];

    protected $fillable = [
        'store_id', 'warehouse_id', 'variant_id', 'type', 'quantity', 'balance_after', 'unit_cost',
        'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Stock movements cannot be deleted.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
