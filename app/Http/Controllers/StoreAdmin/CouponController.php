<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin.marketing.coupons', ['coupons' => Coupon::withCount('usages')->withSum('usages', 'discount_amount')->latest('id')->get()]);
    }

    public function create()
    {
        return $this->form(new Coupon(['type' => 'percent', 'applies_to' => 'all', 'is_active' => true]));
    }

    public function edit(Coupon $coupon)
    {
        return $this->form($coupon->load('products', 'categories'));
    }

    public function store(Request $request)
    {
        $this->save(new Coupon, $request);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->save($coupon, $request);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon saved.');
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->usages()->exists()) {
            $coupon->update(['is_active' => false]);

            return back()->with('status', 'This coupon has been used, so it was switched off instead of deleted.');
        }

        $coupon->delete();

        return back()->with('status', 'Coupon deleted.');
    }

    private function form(Coupon $coupon)
    {
        return view('admin.marketing.coupon-form', [
            'coupon' => $coupon,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function save(Coupon $coupon, Request $request): void
    {
        $storeId = $this->currentStore()->id;
        $request->merge(['code' => strtoupper(trim((string) $request->code))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('coupons', 'code')->where('store_id', $storeId)->ignore($coupon->id)],
            'name' => 'required|string|max:150',
            'type' => 'required|in:'.implode(',', array_keys(Coupon::TYPES)),
            'value' => 'required_unless:type,free_shipping|nullable|numeric|min:0|max:99999999',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'applies_to' => 'required|in:all,products,categories',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'products' => 'array',
            'products.*' => [Rule::exists('products', 'id')->where('store_id', $storeId)],
            'categories' => 'array',
            'categories.*' => [Rule::exists('categories', 'id')->where('store_id', $storeId)],
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'A percentage cannot be more than 100.']);
        }

        $coupon->fill(collect($data)->except(['products', 'categories'])->all() + [
            'value' => $data['value'] ?? 0,
            'first_order_only' => $request->boolean('first_order_only'),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $coupon->products()->sync($data['applies_to'] === 'products' ? ($data['products'] ?? []) : []);
        $coupon->categories()->sync($data['applies_to'] === 'categories' ? ($data['categories'] ?? []) : []);
    }
}
