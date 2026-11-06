<?php

namespace App\Models;

use App\Support\Media;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'product_id', 'path', 'alt', 'sort'];

    public function url(): ?string
    {
        return Media::url($this->path);
    }
}
