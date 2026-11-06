<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/** Platform billing: client subscription invoices and the payments Appharu receives. */
class BillingController extends Controller
{
    public function index(Request $request)
    {
        $invoices = SubscriptionInvoice::with('store', 'payments')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->store, fn ($q, $s) => $q->where('store_id', $s))
            ->orderByRaw("status = 'unpaid' desc")->latest('due_on')
            ->paginate(30)->withQueryString();

        return view('super.billing', [
            'invoices' => $invoices,
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'unpaidTotal' => SubscriptionInvoice::where('status', 'unpaid')->get()->sum(fn ($i) => $i->balance()),
        ]);
    }

    public function payment(Request $request, SubscriptionInvoice $invoice, SubscriptionService $subscriptions)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.max(0.01, $invoice->balance()),
            'method' => 'required|in:khalti,esewa,bank,cash,qr',
            'reference' => 'nullable|string|max:100',
        ]);

        $subscriptions->recordPayment($invoice, (float) $data['amount'], $data['method'], $data['reference'] ?? null, $request->user());

        return back()->with('status', 'Payment recorded for '.$invoice->number.'.');
    }

    public function renewals(SubscriptionService $subscriptions)
    {
        $raised = $subscriptions->generateRenewals();
        $suspended = $subscriptions->enforce();

        return back()->with('status', "Renewal invoices raised: {$raised}. Stores suspended for non-payment: {$suspended}.");
    }
}
