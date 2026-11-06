<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A store's sales invoice. Once issued its figures never change; corrections go on credit notes. */
class Invoice extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id', 'order_id', 'number', 'status', 'issued_at', 'customer_name', 'customer_phone', 'customer_email',
        'billing_address', 'currency', 'subtotal', 'discount_total', 'shipping_total', 'taxable_amount', 'tax_total',
        'tax_label', 'grand_total',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice) {
            if ($invoice->isDirty(array_diff($invoice->getFillable(), ['status']))) {
                throw new \LogicException('An issued invoice cannot be changed. Issue a credit note instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Issued invoices cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
