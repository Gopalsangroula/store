<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Orders for integrations. The token must have been made in THIS store (its name starts
 * with store:<id>:) by someone who still works here with permission to see orders.
 */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeToken($request);

        $orders = Order::with('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->since, fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->latest('placed_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 50))));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $o) => $this->order($o)),
            'meta' => ['page' => $orders->currentPage(), 'pages' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function show(Request $request, string $number)
    {
        $this->authorizeToken($request);

        return response()->json(['data' => $this->order(Order::with('items')->where('number', $number)->firstOrFail())]);
    }

    private function authorizeToken(Request $request): void
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $store = $this->currentStore();

        abort_unless($store->hasFeature('api'), 403, 'API access is not part of this store\'s plan.');
        abort_unless($token && str_starts_with($token->name, 'store:'.$store->id.':') && $token->can('orders:read'), 403, 'This token is not for this store.');
        abort_unless($user->canInStore($store, 'orders.view'), 403, 'This account cannot view orders.');
    }

    private function order(Order $o): array
    {
        return [
            'number' => $o->number,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'payment_method' => $o->payment_method,
            'currency' => $o->currency,
            'totals' => ['subtotal' => (float) $o->subtotal, 'discount' => (float) $o->discount_total, 'shipping' => (float) $o->shipping_total, 'tax' => (float) $o->tax_total, 'total' => (float) $o->grand_total, 'paid' => (float) $o->paid_total],
            'customer' => ['name' => $o->customer_name, 'phone' => $o->customer_phone, 'email' => $o->customer_email],
            'shipping' => ['address' => $o->shipping_address, 'area' => $o->shipping_area, 'city' => $o->shipping_city, 'province' => $o->shipping_province, 'courier' => $o->courier, 'tracking_number' => $o->tracking_number],
            'items' => $o->items->map(fn ($i) => ['sku' => $i->sku, 'name' => $i->product_name, 'variant' => $i->variant_name, 'quantity' => $i->quantity, 'unit_price' => (float) $i->unit_price, 'total' => (float) $i->line_total]),
            'placed_at' => $o->placed_at->toIso8601String(),
            'updated_at' => $o->updated_at->toIso8601String(),
        ];
    }
}
