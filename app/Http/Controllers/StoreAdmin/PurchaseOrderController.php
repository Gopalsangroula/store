<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\DocumentNumber;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return view('admin.inventory.purchase-orders', [
            'orders' => PurchaseOrder::with('supplier', 'warehouse')->withCount('items')->latest('id')->paginate(25),
        ]);
    }

    public function create()
    {
        return view('admin.inventory.purchase-order-form', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->get(),
            'variants' => ProductVariant::with('product')->where('track_inventory', true)->get()
                ->map(fn ($v) => ['id' => $v->id, 'label' => $v->label().' ('.$v->sku.')', 'cost' => (float) $v->cost_price])->sortBy('label')->values(),
        ]);
    }

    public function store(Request $request, DocumentNumber $numbers)
    {
        $storeId = $this->currentStore()->id;
        $data = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('store_id', $storeId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'ordered_on' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:200',
            'items.*.variant_id' => ['required', Rule::exists('product_variants', 'id')->where('store_id', $storeId)],
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:99999999',
        ]);

        $po = DB::transaction(function () use ($data, $numbers) {
            $total = collect($data['items'])->sum(fn ($i) => Money::toMinor($i['unit_cost']) * $i['quantity']);

            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'number' => $numbers->next($this->currentStore(), 'purchase_order'),
                'status' => 'ordered',
                'total' => Money::fromMinor($total),
                'note' => $data['note'] ?? null,
                'ordered_on' => $data['ordered_on'] ?? now()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create(['purchase_order_id' => $po->id] + $item);
            }

            return $po;
        });

        return redirect()->route('admin.purchase-orders.show', $po)->with('status', 'Purchase order '.$po->number.' created.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return view('admin.inventory.purchase-order', ['po' => $purchaseOrder->load('items.variant.product', 'supplier', 'warehouse')]);
    }

    public function receive(PurchaseOrder $purchaseOrder, InventoryService $inventory, AccountingService $accounting)
    {
        if ($purchaseOrder->status !== 'ordered') {
            return back()->with('error', 'Only an open purchase order can be received.');
        }

        DB::transaction(function () use ($purchaseOrder, $inventory, $accounting) {
            $inventory->receivePurchaseOrder($purchaseOrder);
            $accounting->recordStockPurchase($purchaseOrder, Money::toMinor($purchaseOrder->total));
        });

        ActivityLog::record('purchase_order.received', $purchaseOrder);

        return back()->with('status', 'Goods received. Stock has been added to '.$purchaseOrder->warehouse->name.'.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'ordered') {
            return back()->with('error', 'This purchase order can no longer be cancelled.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return back()->with('status', 'Purchase order cancelled.');
    }
}
