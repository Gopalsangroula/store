<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'key', 'value'];
}
