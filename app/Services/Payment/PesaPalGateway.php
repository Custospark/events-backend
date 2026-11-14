<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Support\EventFeeCalculator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PesaPalGateway implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $consumerKey;

    private string $consumerSecret;

    public function __construct()
    {
        $config = config('pesapal');

        $this->baseUrl = ($config['environment'] ?? 'sandbox') === 'production'
            ? (string) $config['base_url_production']
            : (string) $config['base_url_sandbox'];

        $this->consumerKey = (string) ($config['consumer_key'] ?? '');
        $this->consumerSecret = (string) ($config['consumer_secret'] ?? '');
    }

    public function initialize(Order $order, Payment $payment): array
    {
        if ($this->isBypassMode()) {
            return [
                'provider_reference' => 'BYPASS-'.$payment->id,
                'checkout_url' => $this->frontendCheckoutUrl($order),
                'payload' => [
                    'mode' => 'bypass',
                    'merchant_reference' => $order->reference,
                ],
            ];
        }

        $token = $this->accessToken();
        $ipnId = $this->ipnId($token);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", [
                'id' => $order->reference,
                'currency' => strtoupper((string) $order->currency),
                'amount' => (float) $order->total,
                'description' => $payment->transaction_label
                    ?? EventFeeCalculator::bearerCode($order->processing_fee_bearer).' | '.$order->reference,
                'callback_url' => (string) config('pesapal.callback_url'),
                'redirect_mode' => 'TOP_WINDOW',
                'notification_id' => $ipnId,
                'billing_address' => [
                    'email_address' => $order->buyer_email,
                    'phone_number' => (string) $order->buyer_phone,
                    'country_code' => 'UG',
                    'first_name' => $order->buyer_name,
                    'last_name' => '',
                ],
            ]);

        $data = $response->json() ?? [];

        if ($response->failed() || empty($data['redirect_url']) || empty($data['order_tracking_id'])) {
            Log::error('[PesaPal] Order submission failed', [
                'status' => $response->status(),
                'message' => $data['error']['message'] ?? $data['message'] ?? null,
            ]);

            throw new RuntimeException('PesaPal could not start this payment.');
        }

        return [
            'provider_reference' => (string) $data['order_tracking_id'],
            'checkout_url' => (string) $data['redirect_url'],
            'payload' => $data + ['merchant_reference' => $order->reference],
        ];
    }

    public function verify(string $providerReference): array
    {
        if ($this->isBypassMode() || str_starts_with($providerReference, 'BYPASS-')) {
            return [
                'status' => 'successful',
                'payload' => [
                    'mode' => 'bypass',
                    'order_tracking_id' => $providerReference,
                ],
            ];
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $providerReference,
            ]);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            throw new RuntimeException('PesaPal could not verify this payment.');
        }

        $status = match ((int) ($data['status_code'] ?? 0)) {
            1 => 'successful',
            2, 3 => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'payload' => $data + ['order_tracking_id' => $providerReference],
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'status' => 'manual',
            'payload' => [
                'manual_refund_required' => true,
                'message' => 'PesaPal refunds are processed from the PesaPal merchant dashboard.',
                'provider_reference' => $payment->provider_reference,
            ],
        ];
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        if ($this->isBypassMode()) {
            return true;
        }

        return (bool) config('pesapal.enabled', false)
            && $this->consumerKey !== ''
            && $this->consumerSecret !== '';
    }

    public function isBypassMode(): bool
    {
        return app()->environment('local', 'testing')
            && (bool) config('pesapal.bypass', false);
    }

    private function accessToken(): string
    {
        $cacheKey = 'pesapal_token_'.config('pesapal.environment');

        return Cache::remember($cacheKey, (int) config('pesapal.token_cache_ttl', 1800), function (): string {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post("{$this->baseUrl}/api/Auth/RequestToken", [
                    'consumer_key' => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                ]);

            $data = $response->json() ?? [];

            if ($response->failed() || empty($data['token'])) {
                Log::error('[PesaPal] Token request failed', [
                    'status' => $response->status(),
                    'message' => $data['error']['message'] ?? $data['message'] ?? null,
                ]);

                throw new RuntimeException('PesaPal authentication failed. Check the gateway credentials.');
            }

            return (string) $data['token'];
        });
    }

    public function registerIpn(): string
    {
        Cache::forget('pesapal_ipn_id_'.config('pesapal.environment'));

        return $this->registerIpnEndpoint($this->accessToken());
    }

    private function ipnId(string $accessToken): string
    {
        $configured = (string) config('pesapal.ipn_id', '');

        if ($configured !== '') {
            return $configured;
        }

        return Cache::remember(
            'pesapal_ipn_id_'.config('pesapal.environment'),
            86400,
            fn (): string => $this->registerIpnEndpoint($accessToken),
        );
    }

    private function registerIpnEndpoint(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                'url' => (string) config('pesapal.ipn_url'),
                'ipn_notification_type' => 'GET',
            ]);

        $data = $response->json() ?? [];

        if ($response->failed() || empty($data['ipn_id'])) {
            Log::error('[PesaPal] IPN registration failed', [
                'status' => $response->status(),
                'message' => $data['error']['message'] ?? $data['message'] ?? null,
            ]);

            throw new RuntimeException('PesaPal could not register the IPN endpoint.');
        }

        return (string) $data['ipn_id'];
    }

    private function frontendCheckoutUrl(Order $order): string
    {
        $frontend = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontend.'/orders/'.$order->reference;
    }
}
