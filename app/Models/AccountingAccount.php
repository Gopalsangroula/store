<?php

namespace App\Models;

use App\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    use BelongsToStore;

    /** The chart of accounts every new store starts with. Codes are what the services post to. */
    public const DEFAULTS = [
        '1000' => ['Cash in hand', 'asset'],
        '1010' => ['Bank account', 'asset'],
        '1020' => ['Khalti wallet', 'asset'],
        '1100' => ['Customers receivable', 'asset'],
        '1200' => ['Inventory', 'asset'],
        '2100' => ['VAT payable', 'liability'],
        '2200' => ['Customer refunds payable', 'liability'],
        '3000' => ['Owner equity', 'equity'],
        '4000' => ['Sales', 'income'],
        '4100' => ['Shipping income', 'income'],
        '4900' => ['Sales returns and discounts', 'income'],
        '5000' => ['Cost of goods sold', 'expense'],
        '6000' => ['Operating expenses', 'expense'],
    ];

    protected $fillable = ['store_id', 'code', 'name', 'type', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** Positive balance in the account's normal direction (debit for assets and expenses). */
    public function balance(): float
    {
        $debit = (float) $this->lines()->sum('debit');
        $credit = (float) $this->lines()->sum('credit');

        return in_array($this->type, ['asset', 'expense'], true) ? $debit - $credit : $credit - $debit;
    }
}
