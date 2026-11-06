<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Monthly recurring revenue: active subscriptions, yearly ones counted per month.
        $mrr = StoreSubscription::where('status', 'active')->get()
            ->sum(fn ($s) => $s->billing_cycle === 'yearly' ? (float) $s->price / 12 : (float) $s->price);

        return view('super.dashboard', [
            'stores' => Store::count(),
            'active' => Store::where('status', 'active')->count(),
            'suspended' => Store::where('status', 'suspended')->count(),
            'newThisMonth' => Store::where('created_at', '>=', now()->startOfMonth())->count(),
            'mrr' => $mrr,
            'collected' => SubscriptionPayment::where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'outstanding' => SubscriptionInvoice::where('status', 'unpaid')->get()->sum(fn ($i) => $i->balance()),
            'renewals' => StoreSubscription::with('store', 'plan')->where('status', 'active')->whereBetween('ends_at', [now(), now()->addDays(14)])->orderBy('ends_at')->get(),
            'byPlan' => Plan::withCount(['stores' => fn ($q) => $q->where('status', 'active')])->orderBy('sort')->get(),
            'latest' => Store::with('plan', 'owner')->latest()->limit(6)->get(),
            // Across stores only as totals; the platform never needs a store's customer data here.
            'gmv' => DB::table('orders')->whereNot('status', 'cancelled')->where('placed_at', '>=', now()->subDays(30))->sum('grand_total'),
        ]);
    }

    public function activity(Request $request)
    {
        return view('super.activity', [
            'logs' => ActivityLog::with('user', 'store')
                ->when($request->store, fn ($q, $s) => $q->where('store_id', $s))
                ->latest('id')->paginate(50)->withQueryString(),
            'stores' => Store::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function settings()
    {
        return view('super.settings', ['settings' => DB::table('platform_settings')->pluck('value', 'key')]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string|max:30',
            'billing_bank' => 'nullable|string|max:500',
            'maintenance_note' => 'nullable|string|max:300',
        ]);

        foreach ($data as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }

        ActivityLog::record('platform.settings');

        return back()->with('status', 'Platform settings saved.');
    }
}
