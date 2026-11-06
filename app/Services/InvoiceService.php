<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Sales invoices and credit notes, numbered per store and year (INV-2026-000001). */
class InvoiceService
{
    public function __construct(private DocumentNumber $numbers)
    {
    }

    /** Issue the invoice for a paid order. Safe to call twice: one order, one invoice. */
    public function issueFor(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            if ($existing = Invoice::where('order_id', $order->id)->lockForUpdate()->first()) {
                return $existing;
            }

            $order->loadMissing('items', 'store');
            $tax = Money::toMinor($order->tax_total);
            $grand = Money::toMinor($order->grand_total);
            $taxable = $order->tax_inclusive ? $grand - $tax : $grand - $tax;
            $rate = TaxRate::where('is_active', true)->first();

            $invoice = Invoice::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'number' => $this->numbers->next($order->store, 'invoice'),
                'status' => 'issued',
                'issued_at' => now(),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'billing_address' => $order->fullAddress(),
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'discount_total' => $order->discount_total,
                'shipping_total' => $order->shipping_total,
                'taxable_amount' => Money::fromMinor($taxable),
                'tax_total' => $order->tax_total,
                'tax_label' => $rate ? $rate->name.' '.rtrim(rtrim((string) $rate->rate, '0'), '.').'%'.($order->tax_inclusive ? ' (included)' : '') : null,
                'grand_total' => $order->grand_total,
            ]);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => trim($item->product_name.($item->variant_name ? ' - '.$item->variant_name : '')),
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            if (Money::toMinor($order->shipping_total) > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Delivery'.($order->shipping_zone_name ? ' ('.$order->shipping_zone_name.')' : ''),
                    'quantity' => 1,
                    'unit_price' => $order->shipping_total,
                    'line_total' => $order->shipping_total,
                ]);
            }

            return $invoice;
        });
    }

    public function creditNote(Invoice $invoice, float $amount, string $reason, ?User $user = null): CreditNote
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $user) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            $already = Money::toMinor($invoice->creditNotes()->sum('amount'));
            $amountMinor = Money::toMinor($amount);

            if ($amountMinor <= 0 || $already + $amountMinor > Money::toMinor($invoice->grand_total)) {
                throw new \RuntimeException('The credit cannot be more than what is left on the invoice.');
            }

            // Tax comes back in the same proportion it was charged.
            $taxShare = (int) round($amountMinor * Money::toMinor($invoice->tax_total) / max(1, Money::toMinor($invoice->grand_total)));

            $note = CreditNote::create([
                'store_id' => $invoice->store_id,
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'number' => $this->numbers->next($invoice->order->store, 'credit_note'),
                'amount' => Money::fromMinor($amountMinor),
                'tax_amount' => Money::fromMinor($taxShare),
                'reason' => $reason,
                'user_id' => $user?->id,
                'issued_at' => now(),
            ]);

            if ($already + $amountMinor >= Money::toMinor($invoice->grand_total)) {
                $invoice->update(['status' => 'credited']);
            }

            return $note;
        });
    }
}
