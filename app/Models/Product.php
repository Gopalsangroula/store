<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToStore, SoftDeletes;

    protected $fillable = [
        'store_id', 'category_id', 'brand_id', 'name', 'slug', 'badge', 'short_description', 'description',
        'status', 'is_featured', 'is_taxable', 'seo_title', 'seo_description', 'sort',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'is_taxable' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function imageUrl(): ?string
    {
        $image = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();

        return Media::url($image?->path);
    }

    public function minPrice(): float
    {
        $variants = $this->relationLoaded('activeVariants') ? $this->activeVariants : $this->activeVariants()->get();

        return (float) $variants->min('price');
    }
}
