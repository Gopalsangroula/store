<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Support\Facades\Http;

/**
 * Khalti ePayment (KPG-2).
 *
 *  1. initiate: POST epayment/initiate/ -> pidx + payment_url, shopper is sent to Khalti.
 *  2. Khalti sends the shopper back to return_url with pidx and a status in the query.
 *  3. The query string is NOT trusted: we call epayment/lookup/ with the pidx and only
 *     a "Completed" lookup whose amount matches the payment marks it paid.
 *
 * Each store uses its own Khalti merchant secret key (saved encrypted in payment_methods).
 */
class KhaltiGateway
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::where('code', 'khalti')->firstOrFail();
    }

    /** @return string the Khalti page to send the shopper to */
    public function initiate(Payment $payment, Order $order, string $returnUrl, string $websiteUrl): string
    {
        $method = $this->method();
        $amount = Money::toMinor($payment->amount);

        $response = $this->client($method)->post('epayment/initiate/', [
            'return_url' => $returnUrl,
            'website_url' => $websiteUrl,
            'amount' => $amount,
            'purchase_order_id' => $order->number.'-P'.$payment->id,
            'purchase_order_name' => 'Order '.$order->number,
            'customer_info' => array_filter([
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => preg_replace('/\D/', '', $order->customer_phone),
            ]),
        ]);

        $body = $response->json() ?? [];
        app(PaymentService::class)->log($payment, 'khalti.initiate', ['http' => $response->status(), 'body' => $body]);

        if (! $response->successful() || empty($body['pidx']) || empty($body['payment_url'])) {
            throw new \RuntimeException('Khalti could not start the payment. '.($body['detail'] ?? $body['error_key'] ?? 'Please try again.'));
        }

        $payment->update(['gateway_reference' => $body['pidx'], 'status' => 'initiated']);

        return $body['payment_url'];
    }

    /**
     * Ask Khalti what really happened to a pidx.
     *
     * @return array{status: string, transaction_id: ?string, amount: int, raw: array}
     */
    public function lookup(string $pidx): array
    {
        $response = $this->client($this->method())->post('epayment/lookup/', ['pidx' => $pidx]);
        $body = $response->json() ?? [];

        if (! $response->successful() && empty($body['status'])) {
            throw new \RuntimeException('Could not reach Khalti to check the payment.');
        }

        return [
            'status' => (string) ($body['status'] ?? 'Unknown'),
            'transaction_id' => $body['transaction_id'] ?? null,
            'amount' => (int) ($body['total_amount'] ?? 0),
            'raw' => $body,
        ];
    }

    /**
     * Settle a payment from a lookup. Returns completed | pending | failed.
     */
    public function settle(Payment $payment): string
    {
        $result = $this->lookup($payment->gateway_reference);
        $service = app(PaymentService::class);
        $service->log($payment, 'khalti.lookup', $result['raw']);

        if ($result['status'] === 'Completed') {
            if ($result['amount'] !== Money::toMinor($payment->amount)) {
                $service->fail($payment, 'Khalti reported a different amount than the order total. The shop will contact you.', $result['raw']);

                return 'failed';
            }

            $service->complete($payment, $result['transaction_id'], $result['raw']);

            return 'completed';
        }

        if (in_array($result['status'], ['Pending', 'Initiated'], true)) {
            return 'pending';
        }

        // Expired, User canceled, Refunded, Partially Refunded...
        $service->fail($payment, 'Khalti payment '.strtolower($result['status']).'.', $result['raw']);

        return 'failed';
    }

    private function client(PaymentMethod $method): \Illuminate\Http\Client\PendingRequest
    {
        $live = (bool) $method->configValue('live', false);
        $base = $live ? config('platform.khalti.live_url') : config('platform.khalti.sandbox_url');

        return Http::baseUrl($base)
            ->withHeaders(['Authorization' => 'Key '.$method->configValue('secret_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
