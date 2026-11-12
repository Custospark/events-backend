<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\PaymentServiceInterface;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentCallbackController extends Controller
{
    public function __construct(
        protected PaymentServiceInterface $paymentService,
        protected TenantContext $tenantContext,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->tenantContext->clear();

        $reference = (string) $request->query('OrderMerchantReference', '');
        $outcome = 'error';

        try {
            $payment = $this->paymentService->handleWebhook(
                $request->getContent(),
                $request->header('X-Custospark-Signature'),
                array_merge($request->query(), $request->json()->all()),
            );

            $outcome = match ($payment->status) {
                'succeeded' => 'success',
                'failed' => 'failed',
                'requires_review', 'refund_pending' => 'review',
                default => 'pending',
            };
        } catch (Throwable $e) {
            Log::error('[Payment] Callback processing failed', [
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $path = $reference !== '' ? '/orders/'.$reference : '/checkout';
        $separator = str_contains($path, '?') ? '&' : '?';

        return redirect()->away($frontend.$path.$separator.'payment='.$outcome);
    }
}
