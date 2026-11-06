<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'slug', 'logo_path'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
