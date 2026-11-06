<?php

namespace App\Tenancy;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every store-owned model uses this. While a store is set for the request, queries only
 * see that store's rows and new rows are stamped with its id, so a URL with another
 * store's id simply finds nothing. Without a store (super admin, console) nothing is filtered.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $query) {
            $storeId = app(TenantContext::class)->id();

            if ($storeId !== null) {
                $query->where($query->getModel()->getTable().'.store_id', $storeId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->store_id)) {
                $model->store_id = app(TenantContext::class)->id()
                    ?? throw new \LogicException(static::class.' needs a store before it can be saved.');
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
