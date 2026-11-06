<?php

namespace App\Models;

use App\Support\Money;
use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToStore;

    public const STATUSES = [
        'pending' => ['Pending', 'warning'],
        'confirmed' => ['Confirmed', 'info'],
        'processing' => ['Packing', 'primary'],
        'shipped' => ['Shipped', 'primary'],
        'delivered' => ['Delivered', 'success'],
        'cancelled' => ['Cancelled', 'secondary'],
        'returned' => ['Returned', 'dark'],
    ];

    /** Where each status may go next. Anything else is refused. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'shipped', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => ['Unpaid', 'secondary'],
        'pending_verification' => ['Awaiting verification', 'warning'],
        'paid' => ['Paid', 'success'],
        'partially_refunded' => ['Partly refunded', 'info'],
        'refunded' => ['Refunded', 'dark'],
        'failed' => ['Payment failed', 'danger'],
    ];

    protected $fillable = [
        'store_id', 'number', 'access_token', 'customer_id', 'status', 'payment_status', 'payment_method', 'currency',
        'base_currency', 'exchange_rate', 'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'tax_inclusive',
        'grand_total', 'paid_total', 'refunded_total', 'coupon_code', 'customer_name', 'customer_email', 'customer_phone',
        'shipping_province', 'shipping_city', 'shipping_area', 'shipping_address', 'shipping_landmark', 'shipping_zone_id',
        'shipping_zone_name', 'customer_note', 'courier', 'tracking_number', 'stock_committed', 'ip', 'placed_at',
        'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'tax_inclusive' => 'boolean',
            'stock_committed' => 'boolean',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status][0] ?? ucfirst($this->payment_status);
    }

    public function paymentStatusColor(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status][1] ?? 'secondary';
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['paid', 'partially_refunded', 'refunded'], true);
    }

    public function balanceDue(): float
    {
        return max(0, Money::toMinor($this->grand_total) - Money::toMinor($this->paid_total)) / 100;
    }

    public function money(string|float|null $amount): string
    {
        return Money::format($amount, $this->currency);
    }

    public function fullAddress(): string
    {
        return collect([$this->shipping_address, $this->shipping_area, $this->shipping_city, $this->shipping_province])
            ->filter()->implode(', ');
    }
}
