<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

/** Historical rates: a new rate is a new row, so old orders keep the rate they were placed at. */
class ExchangeRate extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'base', 'quote', 'rate', 'effective_at'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime'];
    }
}
