<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\OrderRequest;
use App\Http\Resources\EventCollection;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TenantResource;
use App\Services\Contracts\EventServiceInterface;
use App\Services\Contracts\OrderServiceInterface;
use App\Services\Contracts\PaymentServiceInterface;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends ApiController
{
    public function __construct(
        protected TenantServiceInterface $tenantService,
        protected EventServiceInterface $eventService,
        protected OrderServiceInterface $orderService,
        protected PaymentServiceInterface $paymentService,
    ) {}

    public function discoverEvents(Request $request, TenantContext $context): EventCollection
    {
        $context->clear();

        $filters = [
            'search' => $request->query('search'),
            'city' => $request->query('city'),
            'upcoming' => $request->boolean('upcoming', true),
        ];

        return new EventCollection(
            $this->eventService->discover($filters, $this->perPage($request)),
        );
    }

    public function tenant(string $tenantSlug): TenantResource
    {
        return new TenantResource($this->tenantService->getBySlug($tenantSlug));
    }

    public function events(Request $request, string $tenantSlug): EventCollection
    {
        $tenant = $this->tenantService->getBySlug($tenantSlug);
        $filters = ['status' => 'published', 'search' => $request->query('search')];

        return new EventCollection(
            $this->eventService->getAll((int) $tenant->id, $filters, $this->perPage($request)),
        );
    }

    public function event(string $tenantSlug, string $eventSlug): EventResource
    {
        $tenant = $this->tenantService->getBySlug($tenantSlug);
        $event = $this->eventService->getBySlug((int) $tenant->id, $eventSlug);

        if (! $event->isPublished()) {
            abort(404, 'Event not found.');
        }

        return new EventResource($event->load(['ticketTypes' => fn ($q) => $q->where('status', 'active')]));
    }

    public function createOrder(OrderRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->tenantService->getBySlug($tenantSlug);
        $data = $request->validated();
        $data['user_id'] = $request->user('sanctum')?->id;

        $order = $this->orderService->create((int) $tenant->id, $data);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function orderStatus(string $tenantSlug, string $reference): OrderResource
    {
        $tenant = $this->tenantService->getBySlug($tenantSlug);

        return new OrderResource(
            $this->orderService->getByReference((int) $tenant->id, $reference),
        );
    }

    public function publicOrderStatus(string $reference, TenantContext $context): OrderResource
    {
        $context->clear();

        return new OrderResource(
            $this->orderService->getPublicByReference($reference),
        );
    }

    public function verifyPublicOrder(string $reference, TenantContext $context): JsonResponse
    {
        $context->clear();

        $payment = $this->paymentService->verifyByReference($reference);

        return response()->json([
            'data' => [
                'status' => $payment->status,
            ],
        ]);
    }

    public function currentTenant(TenantContext $context): TenantResource|JsonResponse
    {
        $tenant = $context->get();

        if ($tenant === null) {
            return response()->json(['message' => 'Tenant could not be resolved.'], 404);
        }

        return new TenantResource($tenant);
    }
}
