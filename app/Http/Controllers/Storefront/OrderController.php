<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Services\Payments\KhaltiGateway;
use App\Support\Media;
use Illuminate\Http\Request;

/**
 * Order pages for shoppers. A guest proves an order is theirs with the secret link they got
 * at checkout (?t=...), the same browser session, or order number + phone on the Track page.
 */
class OrderController extends Controller
{
    public function show(Request $request, string $number)
    {
        $order = $this->authorizedOrder($request, $number);
        $order->load(['items', 'histories' => fn ($q) => $q->where('is_customer_visible', true), 'payments', 'invoice']);

        return view('storefront.order', [
            'order' => $order,
            'token' => $order->access_token,
            'method' => PaymentMethod::where('code', $order->payment_method)->first(),
        ]);
    }

    public function pay(Request $request, string $number)
    {
        $order = $this->authorizedOrder($request, $number);

        if ($order->isPaid() || $order->status === 'cancelled' || $order->payment_method === 'cod') {
            return redirect()->route('shop.order', ['number' => $order->number, 't' => $order->access_token]);
        }

        $method = PaymentMethod::where('code', $order->payment_method)->firstOrFail();

        return view('storefront.pay', [
            'order' => $order->load('items'),
            'method' => $method,
            'token' => $order->access_token,
            'pending' => $order->payment_status === 'pending_verification',
            'expiresAt' => $order->reservations()->where('status', 'active')->min('expires_at'),
        ]);
    }

    /** QR or bank transfer: the shopper uploads the screenshot and/or the transaction id. */
    public function submitManual(Request $request, string $number, PaymentService $payments)
    {
        $order = $this->authorizedOrder($request, $number);
        abort_unless(in_array($order->payment_method, ['qr', 'bank'], true), 404);

        if ($order->isPaid() || $order->status === 'cancelled') {
            return redirect()->route('shop.order', ['number' => $order->number, 't' => $order->access_token]);
        }

        $data = $request->validate([
            'reference' => 'nullable|string|max:100|required_without:proof',
            'proof' => 'nullable|required_without:reference|'.Media::IMAGE_RULE,
        ], [
            'reference.required_without' => 'Enter the transaction ID or upload the payment screenshot.',
        ]);

        // Proofs are private: only staff can open them, through the admin.
        $proof = $request->hasFile('proof') ? Media::store($request->file('proof'), $order->store_id, 'payment-proofs', 'local') : null;

        $payments->submitManual($order, $data['reference'] ?? null, $proof);

        return redirect()->route('shop.order', ['number' => $order->number, 't' => $order->access_token])
            ->with('status', 'Thank you! We have received your payment details and will confirm your order after checking them.');
    }

    public function khalti(Request $request, string $number, PaymentService $payments, KhaltiGateway $khalti)
    {
        $order = $this->authorizedOrder($request, $number);
        abort_unless($order->payment_method === 'khalti', 404);

        if ($order->isPaid() || $order->status === 'cancelled') {
            return redirect()->route('shop.order', ['number' => $order->number, 't' => $order->access_token]);
        }

        $payment = $payments->openPayment($order);
        $payment->update(['amount' => $order->balanceDue(), 'status' => 'initiated']);

        try {
            $url = $khalti->initiate(
                $payment,
                $order,
                route('shop.payment.khalti.return'),
                $this->currentStore()->url('/'),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Khalti is not reachable right now. Please try again.');
        }

        return redirect()->away($url);
    }

    /**
     * Khalti sends the shopper back here. The query string is only a hint: the payment is
     * settled from Khalti's own lookup API, and only for a pidx this store issued.
     */
    public function khaltiReturn(Request $request, KhaltiGateway $khalti)
    {
        $pidx = (string) $request->query('pidx');
        $payment = $pidx !== '' ? Payment::where('method', 'khalti')->where('gateway_reference', $pidx)->with('order')->first() : null;

        if (! $payment) {
            return redirect()->route('shop.home')->with('error', 'We could not find that payment.');
        }

        $order = $payment->order;
        $link = ['number' => $order->number, 't' => $order->access_token];

        try {
            $result = $payment->status === 'completed' ? 'completed' : $khalti->settle($payment);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('shop.order', $link)
                ->with('error', 'We could not confirm your Khalti payment yet. If money left your account, it will be matched automatically; please contact us with order '.$order->number.'.');
        }

        return match ($result) {
            'completed' => redirect()->route('shop.order', $link)->with('placed', true)->with('status', 'Payment received. Your order is confirmed!'),
            'pending' => redirect()->route('shop.order', $link)->with('status', 'Khalti is still processing your payment. This page will update once it is confirmed.'),
            default => redirect()->route('shop.order.pay', $link)->with('error', 'The Khalti payment was not completed. You can try again or choose another way to pay.'),
        };
    }

    public function invoice(Request $request, string $number)
    {
        $order = $this->authorizedOrder($request, $number);
        $invoice = $order->invoice()->with('items')->firstOrFail();

        return view('documents.invoice', ['invoice' => $invoice, 'order' => $order, 'store' => $this->currentStore()]);
    }

    public function track()
    {
        return view('storefront.track');
    }

    public function trackLookup(Request $request)
    {
        $data = $request->validate(['number' => 'required|string|max:30', 'phone' => 'required|string|max:20']);
        $phone = substr(preg_replace('/\D/', '', $data['phone']), -10);

        $order = Order::where('number', strtoupper(trim($data['number'])))->where('customer_phone', $phone)->first();

        if (! $order) {
            return back()->withInput()->with('error', 'No order matches that number and phone.');
        }

        return redirect()->route('shop.order', ['number' => $order->number, 't' => $order->access_token]);
    }

    private function authorizedOrder(Request $request, string $number): Order
    {
        $order = Order::where('number', $number)->firstOrFail();

        $allowed = hash_equals($order->access_token, (string) $request->query('t'))
            || in_array($order->number, (array) $request->session()->get('orders', []), true)
            || (auth('customer')->check() && auth('customer')->id() === $order->customer_id);

        abort_unless($allowed, 404);

        // Keep the link working in this browser even after the ?t= is dropped.
        if (! in_array($order->number, (array) $request->session()->get('orders', []), true)) {
            $request->session()->push('orders', $order->number);
        }

        return $order;
    }
}
