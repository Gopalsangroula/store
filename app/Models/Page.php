<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'title', 'slug', 'content', 'in_footer', 'is_published'];

    protected function casts(): array
    {
        return ['in_footer' => 'boolean', 'is_published' => 'boolean'];
    }
}
