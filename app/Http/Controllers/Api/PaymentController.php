<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PaymentCollection;
use App\Http\Resources\PaymentResource;
use App\Services\Contracts\PaymentServiceInterface;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function index(Request $request): PaymentCollection
    {
        return new PaymentCollection(
            $this->paymentService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'order_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $payment): PaymentResource
    {
        return new PaymentResource(
            $this->paymentService->getById($this->tenantId($request), $payment),
        );
    }

    public function verify(Request $request, int $payment): PaymentResource
    {
        return new PaymentResource(
            $this->paymentService->verify($this->tenantId($request), $payment),
        );
    }

    public function markRefunded(Request $request, int $payment): PaymentResource
    {
        return new PaymentResource(
            $this->paymentService->markRefunded($this->tenantId($request), $payment),
        );
    }
}
