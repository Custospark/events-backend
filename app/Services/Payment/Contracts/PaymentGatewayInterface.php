<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * @return array{provider_reference: string, checkout_url: ?string, payload: array<string, mixed>}
     */
    public function initialize(Order $order, Payment $payment): array;

    /**
     * @return array{status: string, payload: array<string, mixed>}
     */
    public function verify(string $providerReference): array;

    /**
     * @return array{status: string, payload: array<string, mixed>}
     */
    public function refund(Payment $payment): array;

    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool;
}
