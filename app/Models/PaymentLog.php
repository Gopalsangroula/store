<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use BelongsToStore;

    public const UPDATED_AT = null;

    protected $fillable = ['store_id', 'payment_id', 'event', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
