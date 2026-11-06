<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'name', 'rate', 'is_inclusive', 'applies_to_shipping', 'is_active'];

    protected function casts(): array
    {
        return ['is_inclusive' => 'boolean', 'applies_to_shipping' => 'boolean', 'is_active' => 'boolean'];
    }
}
