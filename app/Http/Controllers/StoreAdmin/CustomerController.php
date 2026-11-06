<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->sort === 'spent', fn ($q) => $q->orderByDesc('total_spent'), fn ($q) => $q->orderByDesc('last_order_at'))
            ->paginate(30)->withQueryString();

        return view('admin.customers.index', ['customers' => $customers]);
    }

    public function show(Customer $customer)
    {
        return view('admin.customers.show', [
            'customer' => $customer->load('addresses'),
            'orders' => $customer->orders()->withCount('items')->get(),
        ]);
    }
}
