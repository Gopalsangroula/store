<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        $variants = ProductVariant::with(['product', 'inventory'])
            ->where('track_inventory', true)
            ->whereHas('product')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('sku', 'like', "%{$s}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"))))
            ->get()
            ->sortBy(fn ($v) => [$v->product->name, $v->sort])
            ->values();

        if ($request->filter === 'low') {
            $variants = $variants->filter(fn ($v) => $v->available() <= 5)->values();
        } elseif ($request->filter === 'out') {
            $variants = $variants->filter(fn ($v) => $v->available() <= 0)->values();
        }

        $value = $variants->sum(fn ($v) => $v->onHand() * (float) $v->cost_price);

        return view('admin.inventory.index', compact('variants', 'warehouses', 'value'));
    }

    public function adjust(Request $request, InventoryService $inventory)
    {
        $storeId = $this->currentStore()->id;
        $data = $request->validate([
            'variant_id' => ['required', Rule::exists('product_variants', 'id')->where('store_id', $storeId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'mode' => 'required|in:add,remove,set',
            'quantity' => 'required|integer|min:0|max:1000000',
            'reason' => 'required|string|max:200',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $variant = ProductVariant::findOrFail($data['variant_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $current = (int) $variant->inventory()->where('warehouse_id', $warehouse->id)->value('on_hand');

        $delta = match ($data['mode']) {
            'add' => $data['quantity'],
            'remove' => -$data['quantity'],
            'set' => $data['quantity'] - $current,
        };

        if ($delta === 0) {
            return back()->with('status', 'No change.');
        }

        try {
            $inventory->adjust($variant, $warehouse, $delta, $data['reason'], $data['unit_cost'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('stock.adjusted', $variant, ['warehouse' => $warehouse->code, 'change' => $delta, 'reason' => $data['reason']]);

        return back()->with('status', $variant->label().': '.($delta > 0 ? '+' : '').$delta.' in '.$warehouse->name.'.');
    }

    public function transfer(Request $request, InventoryService $inventory)
    {
        $storeId = $this->currentStore()->id;
        $data = $request->validate([
            'variant_id' => ['required', Rule::exists('product_variants', 'id')->where('store_id', $storeId)],
            'from_id' => ['required', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'to_id' => ['required', 'different:from_id', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'quantity' => 'required|integer|min:1|max:1000000',
            'note' => 'nullable|string|max:200',
        ]);

        try {
            $inventory->transfer(ProductVariant::findOrFail($data['variant_id']), Warehouse::findOrFail($data['from_id']), Warehouse::findOrFail($data['to_id']), $data['quantity'], (string) ($data['note'] ?? ''));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Stock moved.');
    }

    public function movements(Request $request)
    {
        $movements = InventoryTransaction::with(['variant.product', 'warehouse', 'user'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->variant, fn ($q, $v) => $q->where('variant_id', $v))
            ->latest('id')
            ->paginate(50)->withQueryString();

        return view('admin.inventory.movements', ['movements' => $movements]);
    }
}
