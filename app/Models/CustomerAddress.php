<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'customer_id', 'name', 'phone', 'province', 'city', 'area', 'address_line', 'landmark', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
