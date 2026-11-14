<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Support\EventFeeCalculator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CustosparkPaymentGateway implements PaymentGatewayInterface
{
    public function initialize(Order $order, Payment $payment): array
    {
        $apiKey = (string) config('custospark.api_key');

        if ($apiKey === '') {
            $reference = 'cs_'.Str::uuid()->toString();

            return [
                'provider_reference' => $reference,
                'checkout_url' => rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/').'/checkout/'.$order->reference,
                'payload' => [
                    'mode' => 'local',
                    'reference' => $reference,
                    'order_reference' => $order->reference,
                ],
            ];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('custospark.timeout', 30))
            ->post(rtrim((string) config('custospark.api_url'), '/').config('custospark.endpoints.initialize'), [
                'amount' => (float) $order->total,
                'currency' => $order->currency,
                'reference' => $order->reference,
                'description' => $payment->transaction_label
                    ?? EventFeeCalculator::bearerCode($order->processing_fee_bearer).' | '.$order->reference,
                'callback_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/checkout/'.$order->reference,
                'metadata' => [
                    'tenant_id' => $order->tenant_id,
                    'event_id' => $order->event_id,
                    'payment_id' => $payment->id,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Custospark could not start this payment.');
        }

        $body = $response->json();

        return [
            'provider_reference' => (string) ($body['reference'] ?? $body['data']['reference'] ?? ''),
            'checkout_url' => $body['checkout_url'] ?? $body['data']['checkout_url'] ?? null,
            'payload' => $body,
        ];
    }

    public function verify(string $providerReference): array
    {
        $apiKey = (string) config('custospark.api_key');

        if ($apiKey === '') {
            return ['status' => 'pending', 'payload' => ['mode' => 'local']];
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('custospark.timeout', 30))
            ->get(rtrim((string) config('custospark.api_url'), '/').config('custospark.endpoints.verify').'/'.$providerReference);

        if ($response->failed()) {
            throw new RuntimeException('Custospark could not verify this payment.');
        }

        $body = $response->json();

        return [
            'status' => (string) ($body['status'] ?? $body['data']['status'] ?? 'pending'),
            'payload' => $body,
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'status' => 'manual',
            'payload' => ['message' => 'Refunds are processed manually.'],
        ];
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        $secret = (string) config('custospark.webhook_secret');

        if ($secret === '') {
            return app()->environment('local', 'testing');
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        $signature = Str::after($signature, 'sha256=');
        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
