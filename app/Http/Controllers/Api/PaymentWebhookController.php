<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\PaymentServiceInterface;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentServiceInterface $paymentService,
        protected TenantContext $tenantContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->tenantContext->clear();

        $payload = array_merge($request->query(), $request->json()->all());

        $payment = $this->paymentService->handleWebhook(
            $request->getContent(),
            $request->header('X-Custospark-Signature'),
            $payload,
        );

        return response()->json([
            'orderNotificationType' => 'IPNCHANGE',
            'orderTrackingId' => $payload['OrderTrackingId'] ?? $payment->provider_reference,
            'orderMerchantReference' => $payload['OrderMerchantReference'] ?? $payment->order?->reference,
            'status' => 200,
        ]);
    }
}
