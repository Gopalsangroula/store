<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\StoreStaff;
use App\Models\User;
use App\Services\StoreProvisioner;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::with(['plan', 'owner', 'domains', 'currentSubscription'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();

        $orders = DB::table('orders')->whereIn('store_id', $stores->pluck('id'))->where('placed_at', '>=', now()->subDays(30))
            ->groupBy('store_id')->selectRaw('store_id, COUNT(*) c')->pluck('c', 'store_id');

        return view('super.stores.index', ['stores' => $stores, 'orders' => $orders]);
    }

    public function create()
    {
        return view('super.stores.create', ['plans' => Plan::where('is_active', true)->orderBy('sort')->get()]);
    }

    public function store(Request $request, StoreProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['required', 'alpha_dash', 'max:40', 'unique:stores,slug', 'not_in:www,admin,api,mail,superadmin,app,store'],
            'domain' => ['nullable', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', 'unique:store_domains,domain'],
            'owner_name' => 'required|string|max:120',
            'owner_email' => 'required|email|max:150',
            'owner_password' => ['nullable', Password::min(10)],
            'phone' => 'nullable|string|max:30',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'trial_days' => 'nullable|integer|min:0|max:90',
        ]);

        $existing = User::where('email', strtolower($data['owner_email']))->exists();

        if (! $existing && empty($data['owner_password'])) {
            return back()->withInput()->withErrors(['owner_password' => 'Set a password for the new owner account.']);
        }

        $data['slug'] = strtolower($data['slug']);
        $data['domain'] = $data['domain'] ? strtolower($data['domain']) : null;
        $data['trial_days'] = (int) ($data['trial_days'] ?? 0);

        $store = $provisioner->create($data);
        ActivityLog::record('store.created', $store, ['plan' => $store->plan?->slug]);

        return redirect()->route('super.stores.show', $store)->with('status', $store->name.' is ready at '.$store->url('/').' — the owner signs in at '.$store->url('/admin').'.');
    }

    public function show(Store $store)
    {
        $store->load('plan', 'owner', 'domains', 'subscriptions.plan', 'subscriptionInvoices');

        return view('super.stores.show', [
            'store' => $store,
            'plans' => Plan::orderBy('sort')->get(),
            'staff' => StoreStaff::withoutGlobalScopes()->where('store_id', $store->id)->with('user', 'role')->get(),
            'stats' => [
                'products' => DB::table('products')->where('store_id', $store->id)->whereNull('deleted_at')->count(),
                'orders' => DB::table('orders')->where('store_id', $store->id)->count(),
                'sales30' => DB::table('orders')->where('store_id', $store->id)->whereNot('status', 'cancelled')->where('placed_at', '>=', now()->subDays(30))->sum('grand_total'),
                'customers' => DB::table('customers')->where('store_id', $store->id)->count(),
            ],
        ]);
    }

    public function update(Request $request, Store $store)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'nullable|email', 'phone' => 'nullable|string|max:30']);
        $store->update($data);

        return back()->with('status', 'Store saved.');
    }

    public function status(Request $request, Store $store)
    {
        $data = $request->validate(['status' => 'required|in:active,suspended', 'reason' => 'nullable|string|max:200']);
        $store->update(['status' => $data['status'], 'suspended_reason' => $data['status'] === 'suspended' ? ($data['reason'] ?: 'Suspended by the platform') : null]);
        ActivityLog::record('store.'.$data['status'], $store, ['reason' => $data['reason'] ?? null]);

        return back()->with('status', $store->name.' is now '.$data['status'].'.');
    }

    public function plan(Request $request, Store $store, SubscriptionService $subscriptions)
    {
        $data = $request->validate(['plan_id' => 'required|exists:plans,id', 'billing_cycle' => 'required|in:monthly,yearly']);
        $subscriptions->subscribe($store, Plan::findOrFail($data['plan_id']), $data['billing_cycle']);
        ActivityLog::record('store.plan_changed', $store, $data);

        return back()->with('status', 'Plan changed and a new subscription invoice raised.');
    }

    public function addDomain(Request $request, Store $store)
    {
        $request->merge(['domain' => strtolower(trim((string) $request->domain))]);
        $data = $request->validate(['domain' => ['required', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)*$/', 'unique:store_domains,domain']]);

        StoreDomain::create(['store_id' => $store->id, 'domain' => $data['domain'], 'is_primary' => $request->boolean('primary')]);

        if ($request->boolean('primary')) {
            StoreDomain::where('store_id', $store->id)->where('domain', '!=', $data['domain'])->update(['is_primary' => false]);
        }

        Cache::forget('store-host:'.$data['domain']);

        return back()->with('status', 'Domain added.');
    }

    public function removeDomain(Store $store, StoreDomain $domain)
    {
        abort_unless($domain->store_id === $store->id, 404);
        Cache::forget('store-host:'.$domain->domain);
        $domain->delete();

        return back()->with('status', 'Domain removed.');
    }

    /** Hand the store to another person: they become owner, the old owner stays as manager. */
    public function owner(Request $request, Store $store)
    {
        $data = $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', strtolower($data['email']))->firstOrFail();

        DB::transaction(function () use ($store, $user) {
            $roles = Role::where('store_id', $store->id)->pluck('id', 'slug');
            StoreStaff::withoutGlobalScopes()->where('store_id', $store->id)->where('user_id', $store->owner_id)->update(['role_id' => $roles['manager']]);
            StoreStaff::withoutGlobalScopes()->updateOrCreate(['store_id' => $store->id, 'user_id' => $user->id], ['role_id' => $roles['owner'], 'status' => 'active']);
            $store->update(['owner_id' => $user->id]);
        });

        ActivityLog::record('store.owner_changed', $store, ['to' => $user->email]);

        return back()->with('status', $user->name.' now owns '.$store->name.'.');
    }
}
