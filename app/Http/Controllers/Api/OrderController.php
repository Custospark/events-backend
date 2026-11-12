<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Services\Contracts\OrderServiceInterface;
use App\Services\Contracts\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        protected OrderServiceInterface $orderService,
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function index(Request $request): OrderCollection
    {
        return new OrderCollection(
            $this->orderService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'event_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $order): OrderResource
    {
        return new OrderResource(
            $this->orderService->getById($this->tenantId($request), $order)
                ->load(['event', 'items.ticketType', 'tickets', 'payments']),
        );
    }

    public function cancel(Request $request, int $order): OrderResource
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return new OrderResource(
            $this->orderService->cancel(
                $this->tenantId($request),
                $order,
                $validated['reason'] ?? 'Cancelled by staff.',
            ),
        );
    }

    public function refund(Request $request, int $order): OrderResource
    {
        $tenantId = $this->tenantId($request);

        $order = $this->orderService->refund($tenantId, $order);

        $payment = $order->payments()->where('status', 'succeeded')->latest()->first();

        if ($payment !== null) {
            $this->paymentService->refund($tenantId, (int) $payment->id);
        }

        return new OrderResource(
            $order->fresh()->load(['event', 'items.ticketType', 'tickets', 'payments']),
        );
    }

    public function expirePending(Request $request): JsonResponse
    {
        return response()->json(['data' => ['expired' => $this->orderService->expirePendingOrders()]]);
    }
}
