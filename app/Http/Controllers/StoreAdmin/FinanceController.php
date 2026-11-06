<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function invoices(Request $request)
    {
        $invoices = Invoice::with('order')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('customer_name', 'like', "%{$s}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.finance.invoices', ['invoices' => $invoices]);
    }

    public function payments(Request $request)
    {
        $payments = Payment::with('order')
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(30)->withQueryString();

        $totals = Payment::where('status', 'completed')->groupBy('method')->selectRaw('method, COUNT(*) c, SUM(amount) total')->get();

        return view('admin.finance.payments', ['payments' => $payments, 'totals' => $totals]);
    }

    public function creditNotes()
    {
        return view('admin.finance.credit-notes', ['notes' => CreditNote::with('invoice', 'order')->latest('id')->paginate(30)]);
    }

    public function accounts(AccountingService $accounting)
    {
        $balances = collect($accounting->trialBalance())->keyBy('code');

        return view('admin.finance.accounts', [
            'accounts' => AccountingAccount::orderBy('code')->get(),
            'balances' => $balances,
            'debits' => $balances->sum('debit'),
            'credits' => $balances->sum('credit'),
        ]);
    }

    public function journal(Request $request)
    {
        $entries = JournalEntry::with('lines.account')
            ->when($request->account, fn ($q, $a) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $a)))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.finance.journal', ['entries' => $entries, 'accounts' => AccountingAccount::orderBy('code')->get()]);
    }

    public function expenses()
    {
        return view('admin.finance.expenses', [
            'expenses' => Expense::with('category')->latest('spent_on')->latest('id')->paginate(30),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'monthTotal' => Expense::where('spent_on', '>=', now()->startOfMonth())->sum('amount'),
        ]);
    }

    public function storeExpense(Request $request, AccountingService $accounting)
    {
        $data = $request->validate([
            'description' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('store_id', $this->currentStore()->id)],
            'paid_from' => 'required|in:cash,bank,khalti',
            'spent_on' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $accounting) {
            $expense = Expense::create($data + ['user_id' => auth()->id()]);
            $accounting->recordExpense($expense);
            ActivityLog::record('expense.recorded', $expense, ['amount' => $data['amount']]);
        });

        return back()->with('status', 'Expense recorded.');
    }
}
