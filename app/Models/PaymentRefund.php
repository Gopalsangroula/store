<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'payment_id', 'amount', 'reason', 'method', 'reference', 'user_id'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
