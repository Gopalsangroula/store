<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('super.plans.index', ['plans' => Plan::withCount('stores')->orderBy('sort')->get()]);
    }

    public function create()
    {
        return view('super.plans.form', ['plan' => new Plan(['is_active' => true, 'features' => array_keys(Plan::FEATURES)])]);
    }

    public function edit(Plan $plan)
    {
        return view('super.plans.form', ['plan' => $plan]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));

        return redirect()->route('super.plans.index')->with('status', 'Plan created.');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('super.plans.index')->with('status', 'Plan saved. Existing subscriptions keep their price until renewal.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'slug' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => 'nullable|string|max:250',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'max_products' => 'nullable|integer|min:1',
            'max_staff' => 'nullable|integer|min:1',
            'max_orders_per_month' => 'nullable|integer|min:1',
            'features' => 'array',
            'features.*' => Rule::in(array_keys(Plan::FEATURES)),
            'sort' => 'nullable|integer|min:0',
        ]);

        $data['features'] = array_values($data['features'] ?? []);
        $data['setup_fee'] = $data['setup_fee'] ?? 0;
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
