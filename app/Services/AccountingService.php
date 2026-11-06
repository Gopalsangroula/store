<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry bookkeeping for a store. Every entry must balance (debits = credits)
 * or it is refused. Entries are never edited; mistakes get a reversing entry.
 */
class AccountingService
{
    /** Where money lands for each payment method. */
    private const CASH_ACCOUNT = ['cod' => '1000', 'khalti' => '1020', 'qr' => '1010', 'bank' => '1010', 'cash' => '1000'];

    /**
     * @param  array<int, array{0: string, 1: int, 2: int}>  $lines  [account code, debit minor, credit minor]
     */
    public function post(string $memo, array $lines, ?Model $reference = null, ?\DateTimeInterface $date = null): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($line) => $line[1] !== 0 || $line[2] !== 0));

        if ($lines === []) {
            return null;
        }

        $debits = array_sum(array_column($lines, 1));
        $credits = array_sum(array_column($lines, 2));

        if ($debits !== $credits) {
            throw new \LogicException("Journal entry does not balance: debits {$debits} vs credits {$credits}.");
        }

        return DB::transaction(function () use ($memo, $lines, $reference, $date) {
            $entry = JournalEntry::create([
                'entry_date' => ($date ?? now())->format('Y-m-d'),
                'memo' => mb_substr($memo, 0, 250),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => auth('web')->id(),
            ]);

            foreach ($lines as [$code, $debit, $credit]) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'store_id' => $entry->store_id,
                    'account_id' => $this->account($code)->id,
                    'debit' => Money::fromMinor($debit),
                    'credit' => Money::fromMinor($credit),
                ]);
            }

            return $entry;
        });
    }

    /** Invoice issued: the customer owes us; revenue, shipping and VAT are recognised. Cost of goods moves out of stock. */
    public function recordSale(Invoice $invoice, Order $order): void
    {
        if (JournalEntry::where('reference_type', $invoice->getMorphClass())->where('reference_id', $invoice->id)->exists()) {
            return;
        }

        $grand = Money::toMinor($invoice->grand_total);
        $tax = Money::toMinor($invoice->tax_total);
        $shipping = Money::toMinor($invoice->shipping_total);
        $sales = $grand - $tax - $shipping;
        $cost = (int) $order->items->sum(fn ($item) => Money::toMinor($item->unit_cost) * $item->quantity);

        $this->post('Sale '.$invoice->number, [
            ['1100', $grand, 0],
            ['4000', 0, $sales],
            ['4100', 0, $shipping],
            ['2100', 0, $tax],
            ['5000', $cost, 0],
            ['1200', 0, $cost],
        ], $invoice, $invoice->issued_at);
    }

    /** Money received against the customer's balance. */
    public function recordReceipt(Payment $payment): void
    {
        $amount = Money::toMinor($payment->amount);

        $this->post('Payment received ('.$payment->methodLabel().') '.$payment->order->number, [
            [self::CASH_ACCOUNT[$payment->method] ?? '1010', $amount, 0],
            ['1100', 0, $amount],
        ], $payment);
    }

    /** A credit note reverses revenue and VAT; the refund itself pays the money out. */
    public function recordCreditNote(CreditNote $note): void
    {
        $amount = Money::toMinor($note->amount);
        $tax = Money::toMinor($note->tax_amount);

        $this->post('Credit note '.$note->number, [
            ['4900', $amount - $tax, 0],
            ['2100', $tax, 0],
            ['1100', 0, $amount],
        ], $note);
    }

    public function recordRefund(PaymentRefund $refund): void
    {
        $amount = Money::toMinor($refund->amount);

        $this->post('Refund paid ('.$refund->method.')', [
            ['1100', $amount, 0],
            [self::CASH_ACCOUNT[$refund->method] ?? '1010', 0, $amount],
        ], $refund);
    }

    public function recordExpense(Expense $expense): void
    {
        $amount = Money::toMinor($expense->amount);

        $this->post('Expense: '.$expense->description, [
            ['6000', $amount, 0],
            [self::CASH_ACCOUNT[$expense->paid_from] ?? '1000', 0, $amount],
        ], $expense, $expense->spent_on);
    }

    public function recordStockPurchase(Model $purchaseOrder, int $amountMinor): void
    {
        $this->post('Stock purchased '.$purchaseOrder->number, [
            ['1200', $amountMinor, 0],
            ['1010', 0, $amountMinor],
        ], $purchaseOrder);
    }

    /** Trial balance: debit and credit totals per account, which must be equal overall. */
    public function trialBalance(): array
    {
        $totals = JournalEntryLine::query()
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entry_lines.store_id', app(\App\Tenancy\TenantContext::class)->id())
            ->groupBy('accounting_accounts.id', 'accounting_accounts.code', 'accounting_accounts.name', 'accounting_accounts.type')
            ->orderBy('accounting_accounts.code')
            ->selectRaw('accounting_accounts.code, accounting_accounts.name, accounting_accounts.type, SUM(debit) as debit, SUM(credit) as credit')
            ->get();

        return $totals->all();
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::firstOrCreate(
            ['code' => $code],
            ['name' => AccountingAccount::DEFAULTS[$code][0] ?? 'Account '.$code, 'type' => AccountingAccount::DEFAULTS[$code][1] ?? 'asset', 'is_system' => true]
        );
    }
}
