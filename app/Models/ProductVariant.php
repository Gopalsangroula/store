<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id', 'product_id', 'name', 'sku', 'price', 'compare_at_price', 'cost_price', 'image_path', 'swatch',
        'track_inventory', 'allow_preorder', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return ['track_inventory' => 'boolean', 'allow_preorder' => 'boolean', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'variant_id');
    }

    /** Sellable quantity across warehouses: on hand minus what open orders have reserved. */
    public function available(): int
    {
        $rows = $this->relationLoaded('inventory') ? $this->inventory : $this->inventory()->get();

        return (int) $rows->sum(fn (Inventory $row) => max(0, $row->on_hand - $row->reserved));
    }

    public function onHand(): int
    {
        $rows = $this->relationLoaded('inventory') ? $this->inventory : $this->inventory()->get();

        return (int) $rows->sum('on_hand');
    }

    /** in_stock | low | preorder | out */
    public function stockState(): string
    {
        if (! $this->track_inventory) {
            return 'in_stock';
        }

        $available = $this->available();

        if ($available <= 0) {
            return $this->allow_preorder ? 'preorder' : 'out';
        }

        return $available <= 5 ? 'low' : 'in_stock';
    }

    public function canBuy(int $quantity = 1): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! $this->track_inventory || $this->allow_preorder || $this->available() >= $quantity;
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image_path) ?? $this->product?->imageUrl();
    }

    public function label(): string
    {
        return trim($this->product->name.($this->name ? ' - '.$this->name : ''));
    }
}
