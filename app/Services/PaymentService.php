<?php

namespace App\Services;

use App\Exceptions\PaidOrderUnfulfillableException;
use App\Models\Payment;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Contracts\OrderServiceInterface;
use App\Services\Contracts\PaymentServiceInterface;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Support\PaymentReconciler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentService implements PaymentServiceInterface
{
    private const SUCCESS_STATUSES = ['success', 'succeeded', 'paid', 'completed', 'successful'];

    private const FAILED_STATUSES = ['failed', 'cancelled', 'canceled', 'declined', 'expired'];

    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected PaymentGatewayInterface $paymentGateway,
        protected OrderServiceInterface $orderService,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Payment
    {
        $payment = $this->paymentRepository->find($tenantId, $id);

        if ($payment === null) {
            throw new RuntimeException('Payment not found.');
        }

        return $payment;
    }

    public function handleWebhook(string $rawPayload, ?string $signature, array $payload): Payment
    {
        if (isset($payload['OrderTrackingId']) || isset($payload['order_tracking_id'])) {
            return $this->handlePesaPalCallback($payload);
        }

        if (! $this->paymentGateway->verifyWebhookSignature($rawPayload, $signature)) {
            throw new RuntimeException('Invalid payment webhook signature.');
        }

        $reference = $payload['reference']
            ?? $payload['data']['reference']
            ?? $payload['provider_reference']
            ?? null;

        if (! is_string($reference) || $reference === '') {
            throw new RuntimeException('Webhook is missing the payment reference.');
        }

        $payment = $this->paymentRepository->findByProviderReference($reference);

        if ($payment === null) {
            throw new RuntimeException('Payment not found for this webhook.');
        }

        if ($payment->isSuccessful()) {
            return $payment;
        }

        $status = strtolower((string) ($payload['status'] ?? $payload['data']['status'] ?? 'pending'));

        return $this->applyStatus($payment, $status, $payload, $reference);
    }

    public function verify(int $tenantId, int $id): Payment
    {
        $payment = $this->getById($tenantId, $id);

        if ($payment->provider_reference === null) {
            throw new RuntimeException('This payment has not been initialized with the payment gateway.');
        }

        $result = $this->paymentGateway->verify($payment->provider_reference);

        return $this->applyStatus($payment, strtolower($result['status']), $result['payload'], $payment->provider_reference);
    }

    public function verifyByReference(string $reference): Payment
    {
        $order = $this->orderRepository->findByReference($reference);

        if ($order === null || $order->tenant === null || ! $order->tenant->isActive()) {
            throw new RuntimeException('Order not found.');
        }

        $payment = $this->paymentRepository->findByOrderId((int) $order->id);

        if ($payment === null) {
            throw new RuntimeException('Payment not found for this order.');
        }

        if ($payment->isSuccessful()) {
            return $payment;
        }

        if ($payment->provider_reference === null) {
            throw new RuntimeException('This payment has not been initialized with the payment gateway.');
        }

        $result = $this->paymentGateway->verify((string) $payment->provider_reference);

        return $this->applyStatus($payment, strtolower($result['status']), $result['payload'], (string) $payment->provider_reference);
    }

    public function refund(int $tenantId, int $id): Payment
    {
        $payment = $this->getById($tenantId, $id);

        if (! $payment->isSuccessful()) {
            throw new RuntimeException('Only successful payments can be refunded.');
        }

        $result = $this->paymentGateway->refund($payment);
        $status = strtolower((string) ($result['status'] ?? 'manual'));

        $payload = array_merge($payment->payload ?? [], $result['payload'] ?? [], [
            'refund_requested_at' => now()->toIso8601String(),
            'refund_status' => $status,
        ]);

        return $this->paymentRepository->update($payment, [
            'status' => $status === 'refunded' ? 'refunded' : 'refund_pending',
            'payload' => $payload,
        ]);
    }

    public function markRefunded(int $tenantId, int $id): Payment
    {
        $payment = $this->getById($tenantId, $id);

        if (! in_array($payment->status, ['refund_pending', 'succeeded'], true)) {
            throw new RuntimeException('This payment is not awaiting a refund.');
        }

        return $this->paymentRepository->update($payment, [
            'status' => 'refunded',
            'payload' => array_merge($payment->payload ?? [], [
                'refunded_at' => now()->toIso8601String(),
                'refund_completed_manually' => true,
            ]),
        ]);
    }

    public function reconcilePending(int $limit = 50): array
    {
        $counts = ['checked' => 0, 'succeeded' => 0, 'failed' => 0, 'pending' => 0, 'review' => 0, 'errors' => 0];

        foreach ($this->paymentRepository->pendingForReconciliation($limit) as $payment) {
            $counts['checked']++;

            try {
                $result = $this->paymentGateway->verify((string) $payment->provider_reference);
                $payment = $this->applyStatus(
                    $payment,
                    strtolower($result['status']),
                    $result['payload'],
                    (string) $payment->provider_reference,
                );

                match ($payment->status) {
                    'succeeded' => $counts['succeeded']++,
                    'failed' => $counts['failed']++,
                    'requires_review', 'refund_pending' => $counts['review']++,
                    default => $counts['pending']++,
                };
            } catch (Throwable $e) {
                $counts['errors']++;

                Log::error('[Payment] Reconciliation failed', [
                    'payment_id' => $payment->id,
                    'provider_reference' => $payment->provider_reference,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    private function handlePesaPalCallback(array $payload): Payment
    {
        $trackingId = (string) ($payload['OrderTrackingId'] ?? $payload['order_tracking_id'] ?? '');
        $merchantReference = (string) ($payload['OrderMerchantReference'] ?? $payload['order_merchant_reference'] ?? '');

        if ($trackingId === '' || $merchantReference === '') {
            throw new RuntimeException('PesaPal notification is missing the transaction reference.');
        }

        $order = $this->orderRepository->findByReference($merchantReference);

        if ($order === null) {
            throw new RuntimeException('Order not found for this PesaPal notification.');
        }

        $payment = $this->paymentRepository->findByOrderId((int) $order->id);

        if ($payment === null) {
            throw new RuntimeException('Payment not found for this PesaPal notification.');
        }

        if ($payment->isSuccessful()) {
            return $payment;
        }

        if ($payment->provider_reference === null || str_starts_with((string) $payment->provider_reference, 'BYPASS-')) {
            $payment = $this->paymentRepository->update($payment, ['provider_reference' => $trackingId]);
        }

        $result = $this->paymentGateway->verify($trackingId);

        return $this->applyStatus($payment, strtolower($result['status']), $result['payload'], $trackingId);
    }

    private function applyStatus(Payment $payment, string $status, array $payload, string $reference): Payment
    {
        if (in_array($status, self::SUCCESS_STATUSES, true)) {
            $reconciliation = $this->reconcile($payment, $payload, $status);
            $blocking = in_array(
                $reconciliation['code'],
                ['MISSING_AMOUNT', 'CURRENCY_MISMATCH', 'AMOUNT_MISMATCH'],
                true,
            );

            if ($blocking) {
                Log::critical('[Payment] Gateway amount or currency mismatch', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'expected_amount' => $reconciliation['expected_amount'],
                    'gateway_amount' => $reconciliation['gateway_amount'],
                    'gateway_currency' => $reconciliation['gateway_currency'],
                    'reason' => $reconciliation['code'],
                ]);

                return $this->paymentRepository->update($payment, [
                    'status' => 'requires_review',
                    'reconciliation_code' => $reconciliation['code'],
                    'reconciliation' => $reconciliation,
                    'payload' => array_merge($payment->payload ?? [], $payload, [
                        'review_reason' => $reconciliation['code'],
                    ]),
                ]);
            }

            if ($payment->order === null) {
                throw new RuntimeException('Payment is not linked to an order.');
            }

            try {
                $this->orderService->markPaid($payment->order, $reference);
            } catch (PaidOrderUnfulfillableException $e) {
                Log::error('[Payment] Paid order could not be fulfilled', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'message' => $e->getMessage(),
                ]);

                return $this->paymentRepository->update($payment, [
                    'status' => 'refund_pending',
                    'reconciliation_code' => $reconciliation['code'],
                    'reconciliation' => $reconciliation,
                    'payload' => array_merge($payment->payload ?? [], $payload, [
                        'refund_reason' => $e->getMessage(),
                    ]),
                ]);
            }

            return $this->paymentRepository->update($payment, [
                'status' => 'succeeded',
                'paid_at' => $payment->paid_at ?? now(),
                'reconciliation_code' => $reconciliation['code'],
                'reconciliation' => $reconciliation,
                'payload' => array_merge($payment->payload ?? [], $payload),
            ]);
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            $payment = $this->paymentRepository->update($payment, [
                'status' => 'failed',
                'payload' => array_merge($payment->payload ?? [], $payload),
            ]);

            if ($payment->order !== null && ! $payment->order->isPaid()) {
                try {
                    $this->orderService->cancel((int) $payment->order->tenant_id, (int) $payment->order->id, 'Payment failed.');
                } catch (Throwable $e) {
                    Log::warning('[Payment] Failed payment could not cancel order', [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $payment->fresh();
        }

        return $this->paymentRepository->update($payment, [
            'payload' => array_merge($payment->payload ?? [], $payload),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reconcile(Payment $payment, array $payload, string $gatewayStatus): array
    {
        $order = $payment->relationLoaded('order')
            ? $payment->order
            : $payment->order()->first();

        return PaymentReconciler::reconcile($order?->toArray() ?? [], $payload, $gatewayStatus);
    }
}
