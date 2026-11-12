<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\TicketTypeRequest;
use App\Http\Resources\TicketTypeCollection;
use App\Http\Resources\TicketTypeResource;
use App\Services\Contracts\TicketTypeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTypeController extends ApiController
{
    public function __construct(
        protected TicketTypeServiceInterface $ticketTypeService,
    ) {}

    public function index(Request $request): TicketTypeCollection
    {
        return new TicketTypeCollection(
            $this->ticketTypeService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'event_id']),
                $this->perPage($request),
            ),
        );
    }

    public function store(TicketTypeRequest $request): JsonResponse
    {
        $ticketType = $this->ticketTypeService->create($this->tenantId($request), $request->validated());

        return (new TicketTypeResource($ticketType))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $ticketType): TicketTypeResource
    {
        return new TicketTypeResource(
            $this->ticketTypeService->getById($this->tenantId($request), $ticketType),
        );
    }

    public function update(TicketTypeRequest $request, int $ticketType): TicketTypeResource
    {
        return new TicketTypeResource(
            $this->ticketTypeService->update($this->tenantId($request), $ticketType, $request->validated()),
        );
    }

    public function destroy(Request $request, int $ticketType): JsonResponse
    {
        $this->ticketTypeService->delete($this->tenantId($request), $ticketType);

        return response()->json(null, 204);
    }
}
