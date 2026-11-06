<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;

/** The store owner's view of what they owe the platform (read-only; payments are recorded by Appharu). */
class BillingController extends Controller
{
    public function index()
    {
        $store = $this->currentStore()->load('plan', 'currentSubscription.plan');

        return view('admin.billing', [
            'subscription' => $store->currentSubscription,
            'invoices' => SubscriptionInvoice::where('store_id', $store->id)->latest('id')->get(),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'usage' => [
                'products' => \App\Models\Product::count(),
                'staff' => \App\Models\StoreStaff::count(),
                'orders' => \App\Models\Order::where('placed_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }
}
