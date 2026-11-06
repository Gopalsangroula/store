<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\Payments\KhaltiGateway;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::withCount('items')
            ->when($request->q, function ($q, $s) {
                $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('customer_name', 'like', "%{$s}%")->orWhere('customer_phone', 'like', "%{$s}%"));
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment, fn ($q, $p) => $q->where('payment_status', $p)->when($p === 'pending_verification', fn ($w) => $w->whereNot('status', 'cancelled')))
            ->when($request->method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->from, fn ($q, $d) => $q->whereDate('placed_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('placed_at', '<=', $d))
            ->latest('placed_at')
            ->paginate(30)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'counts' => Order::groupBy('status')->selectRaw('status, count(*) c')->pluck('c', 'status'),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items', 'histories.user', 'payments.verifier', 'payments.refunds', 'invoice.creditNotes', 'customer']);

        return view('admin.orders.show', [
            'order' => $order,
            'next' => \App\Models\Order::TRANSITIONS[$order->status] ?? [],
            'previousOrders' => $order->customer_id ? Order::where('customer_id', $order->customer_id)->whereKeyNot($order->id)->count() : 0,
        ]);
    }

    public function status(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Order::STATUSES)),
            'note' => 'nullable|string|max:1000',
            'courier' => 'nullable|string|max:60',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        if ($data['status'] === 'shipped' && $order->payment_method !== 'cod' && ! $order->isPaid()) {
            return back()->with('error', 'This order is not paid yet. Verify the payment before shipping.');
        }

        try {
            $orders->changeStatus($order, $data['status'], $data['note'] ?? null, $request->user(), $data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Order marked '.Order::STATUSES[$data['status']][0].'.');
    }

    public function note(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate(['note' => 'required|string|max:1000', 'visible' => 'boolean']);
        $orders->history($order, null, 'note', $data['note'], $request->user(), $request->boolean('visible'));

        return back()->with('status', 'Note added.');
    }

    public function verifyPayment(Request $request, Payment $payment, PaymentService $payments)
    {
        abort_unless(in_array($payment->status, ['pending_verification', 'initiated', 'failed'], true) && $payment->method !== 'khalti', 422, 'This payment cannot be verified by hand.');
        $data = $request->validate(['transaction_id' => 'nullable|string|max:100']);

        if ($payment->order->status === 'cancelled') {
            return back()->with('error', 'This order is cancelled. Refund the customer instead of verifying the payment.');
        }

        $payments->complete($payment, $data['transaction_id'] ?: $payment->payer_reference, ['verified_manually' => true], $request->user());

        return back()->with('status', 'Payment verified. The order is confirmed and the invoice issued.');
    }

    public function rejectPayment(Request $request, Payment $payment, PaymentService $payments)
    {
        $data = $request->validate(['reason' => 'required|string|max:300']);
        $payments->fail($payment, 'Payment could not be verified: '.$data['reason'], [], $request->user());

        return back()->with('status', 'Payment marked as not received. The customer can submit it again from their order page.');
    }

    public function recheckKhalti(Payment $payment, KhaltiGateway $khalti)
    {
        abort_unless($payment->method === 'khalti' && $payment->gateway_reference, 404);

        try {
            $result = $khalti->settle($payment);
        } catch (\Throwable $e) {
            return back()->with('error', 'Khalti could not be reached: '.$e->getMessage());
        }

        return back()->with('status', 'Khalti says this payment is '.$result.'.');
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, ['Content-Security-Policy' => "default-src 'none'; img-src 'self'"]);
    }

    public function refund(Request $request, Order $order, RefundService $refunds)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:300',
            'method' => 'required|in:khalti,bank,cash,qr',
            'reference' => 'nullable|string|max:100',
            'restock' => 'array',
            'restock.*' => 'integer|min:0',
        ]);

        try {
            $refunds->refund($order, (float) $data['amount'], $data['reason'], $data['method'], $data['reference'] ?? null, $data['restock'] ?? [], $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Refund recorded and credit note issued. Remember to send the money back through '.$data['method'].'.');
    }

    public function invoice(Order $order)
    {
        $invoice = $order->invoice()->with('items')->first();

        if (! $invoice) {
            return back()->with('error', 'The invoice is issued when the order is paid.');
        }

        return view('documents.invoice', ['invoice' => $invoice, 'order' => $order, 'store' => $this->currentStore()]);
    }

    public function slip(Order $order)
    {
        return view('documents.slip', ['order' => $order->load('items'), 'store' => $this->currentStore()]);
    }

    /** Carts with items that were never ordered, newest first, for follow-up calls. */
    public function carts()
    {
        $carts = Cart::with('items.variant.product', 'customer')
            ->whereNull('converted_at')
            ->whereHas('items')
            ->where('updated_at', '<', now()->subHour())
            ->latest('updated_at')
            ->paginate(30);

        return view('admin.orders.carts', ['carts' => $carts]);
    }
}
