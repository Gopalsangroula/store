<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, per-store sequential numbers (orders, invoices, credit notes, purchase orders).
 * The counter row is locked for the rest of the surrounding transaction, so two checkouts
 * at the same moment can never get the same number. Never MAX(id) + 1.
 */
class DocumentNumber
{
    private const FORMATS = [
        'order' => ['ORD', 6],
        'invoice' => ['INV', 6],
        'credit_note' => ['CN', 6],
        'purchase_order' => ['PO', 4],
    ];

    public function next(Store $store, string $type): string
    {
        [$prefix, $digits] = self::FORMATS[$type] ?? throw new \InvalidArgumentException("Unknown document type {$type}");

        if ($type === 'order' && ($custom = $store->setting('order_prefix'))) {
            $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $custom));
        }

        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($store, $type, $year, $prefix, $digits) {
            DB::table('document_sequences')->insertOrIgnore([
                'store_id' => $store->id, 'type' => $type, 'year' => $year, 'next_number' => 1,
            ]);

            $row = DB::table('document_sequences')
                ->where(['store_id' => $store->id, 'type' => $type, 'year' => $year])
                ->lockForUpdate()
                ->first();

            DB::table('document_sequences')->where('id', $row->id)->update(['next_number' => $row->next_number + 1]);

            return sprintf('%s-%d-%0'.$digits.'d', $prefix, $year, $row->next_number);
        });
    }

    /** Platform-wide numbers (subscription invoices), outside any store. */
    public function nextPlatform(string $key, string $prefix): string
    {
        $year = now()->format('Y');
        $sequenceKey = $key.'-'.$year;

        return DB::transaction(function () use ($sequenceKey, $prefix, $year) {
            DB::table('platform_sequences')->insertOrIgnore(['key' => $sequenceKey, 'next_number' => 1]);
            $row = DB::table('platform_sequences')->where('key', $sequenceKey)->lockForUpdate()->first();
            DB::table('platform_sequences')->where('key', $sequenceKey)->update(['next_number' => $row->next_number + 1]);

            return sprintf('%s-%s-%04d', $prefix, $year, $row->next_number);
        });
    }
}
