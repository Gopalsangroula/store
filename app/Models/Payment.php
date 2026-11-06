<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToStore;

    public const STATUSES = [
        'initiated' => ['Started', 'secondary'],
        'pending_verification' => ['Awaiting verification', 'warning'],
        'completed' => ['Completed', 'success'],
        'failed' => ['Failed', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
        'refunded' => ['Refunded', 'dark'],
    ];

    protected $fillable = [
        'store_id', 'order_id', 'method', 'amount', 'currency', 'status', 'gateway_reference', 'transaction_id',
        'payer_reference', 'proof_path', 'gateway_response', 'verified_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['gateway_response' => 'array', 'completed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->latest('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function methodLabel(): string
    {
        return PaymentMethod::CODES[$this->method] ?? ucfirst($this->method);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }
}
