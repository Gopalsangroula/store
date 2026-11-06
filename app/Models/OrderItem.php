<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A snapshot of one line as it was sold. Product edits never reach it. */
class OrderItem extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id', 'order_id', 'product_id', 'variant_id', 'product_name', 'variant_name', 'sku', 'image_path',
        'unit_price', 'unit_cost', 'quantity', 'discount_amount', 'tax_amount', 'line_total', 'is_preorder',
    ];

    protected function casts(): array
    {
        return ['is_preorder' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image_path);
    }
}
