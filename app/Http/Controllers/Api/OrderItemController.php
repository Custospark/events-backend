<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\OrderItemRequest;
use App\Http\Resources\OrderItemCollection;
use App\Http\Resources\OrderItemResource;
use App\Services\Contracts\OrderItemServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends ApiController
{
    public function __construct(
        protected OrderItemServiceInterface $orderItemService,
    ) {}

    public function index(Request $request): OrderItemCollection
    {
        return new OrderItemCollection(
            $this->orderItemService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'order_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $orderItem): OrderItemResource
    {
        return new OrderItemResource(
            $this->orderItemService->getById($this->tenantId($request), $orderItem),
        );
    }

    public function store(OrderItemRequest $request): JsonResponse
    {
        $orderItem = $this->orderItemService->create(
            $this->tenantId($request),
            $request->validated(),
        );

        return (new OrderItemResource($orderItem))->response()->setStatusCode(201);
    }

    public function update(OrderItemRequest $request, int $orderItem): OrderItemResource
    {
        $orderItem = $this->orderItemService->update(
            $this->tenantId($request),
            $orderItem,
            $request->validated(),
        );

        return new OrderItemResource($orderItem);
    }

    public function destroy(Request $request, int $orderItem): JsonResponse
    {
        $this->orderItemService->delete($this->tenantId($request), $orderItem);

        return response()->json(null, 204);
    }
}
