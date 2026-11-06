<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use BelongsToStore;

    public const UPDATED_AT = null;

    protected $fillable = ['store_id', 'order_id', 'from_status', 'to_status', 'note', 'is_customer_visible', 'user_id'];

    protected function casts(): array
    {
        return ['is_customer_visible' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return Order::STATUSES[$this->to_status][0]
            ?? Order::PAYMENT_STATUSES[$this->to_status][0]
            ?? ucfirst(str_replace('_', ' ', $this->to_status));
    }
}
