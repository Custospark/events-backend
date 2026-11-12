<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\EventRequest;
use App\Http\Resources\EventCollection;
use App\Http\Resources\EventResource;
use App\Http\Resources\TicketTypeCollection;
use App\Services\Contracts\EventServiceInterface;
use App\Services\Contracts\TicketTypeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends ApiController
{
    public function __construct(
        protected EventServiceInterface $eventService,
        protected TicketTypeServiceInterface $ticketTypeService,
    ) {}

    public function index(Request $request): EventCollection
    {
        return new EventCollection(
            $this->eventService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'event_id']),
                $this->perPage($request),
            ),
        );
    }

    public function store(EventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($this->tenantId($request), $request->validated());

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $event): EventResource
    {
        return new EventResource(
            $this->eventService->getById($this->tenantId($request), $event)->load('ticketTypes'),
        );
    }

    public function update(EventRequest $request, int $event): EventResource
    {
        return new EventResource(
            $this->eventService->update($this->tenantId($request), $event, $request->validated()),
        );
    }

    public function destroy(Request $request, int $event): JsonResponse
    {
        $this->eventService->delete($this->tenantId($request), $event);

        return response()->json(null, 204);
    }

    public function publish(Request $request, int $event): EventResource
    {
        return new EventResource(
            $this->eventService->publish($this->tenantId($request), $event),
        );
    }

    public function unpublish(Request $request, int $event): EventResource
    {
        return new EventResource(
            $this->eventService->unpublish($this->tenantId($request), $event),
        );
    }

    public function ticketTypes(Request $request, int $event): TicketTypeCollection
    {
        return new TicketTypeCollection(
            $this->ticketTypeService->getForEvent($this->tenantId($request), $event),
        );
    }
}
