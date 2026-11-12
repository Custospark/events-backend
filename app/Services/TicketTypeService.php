<?php

namespace App\Services;

use App\Models\TicketType;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Services\Contracts\TicketTypeServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class TicketTypeService implements TicketTypeServiceInterface
{
    public function __construct(
        protected TicketTypeRepositoryInterface $ticketTypeRepository,
        protected EventRepositoryInterface $eventRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->ticketTypeRepository->all($tenantId, $filters, $perPage);
    }

    public function getForEvent(int $tenantId, int $eventId): Collection
    {
        return $this->ticketTypeRepository->allForEvent($tenantId, $eventId);
    }

    public function getById(int $tenantId, int $id): TicketType
    {
        $ticketType = $this->ticketTypeRepository->find($tenantId, $id);

        if ($ticketType === null) {
            throw new RuntimeException('Ticket type not found.');
        }

        return $ticketType;
    }

    public function create(int $tenantId, array $data): TicketType
    {
        $event = $this->eventRepository->find($tenantId, $data['event_id']);

        if ($event === null) {
            throw new RuntimeException('Event not found for this tenant.');
        }

        if (($data['min_per_order'] ?? 1) > ($data['max_per_order'] ?? 10)) {
            throw new RuntimeException('Minimum tickets per order cannot exceed the maximum.');
        }

        $data['tenant_id'] = $tenantId;
        $data['currency'] = $data['currency'] ?? $event->currency;
        $data['quantity_sold'] = 0;

        return $this->ticketTypeRepository->create($data);
    }

    public function update(int $tenantId, int $id, array $data): TicketType
    {
        $ticketType = $this->getById($tenantId, $id);
        unset($data['tenant_id'], $data['quantity_sold'], $data['event_id']);

        if (isset($data['quantity_total']) && $data['quantity_total'] < $ticketType->quantity_sold) {
            throw new RuntimeException('Ticket quantity cannot be lower than tickets already sold.');
        }

        $min = $data['min_per_order'] ?? $ticketType->min_per_order;
        $max = $data['max_per_order'] ?? $ticketType->max_per_order;

        if ($min > $max) {
            throw new RuntimeException('Minimum tickets per order cannot exceed the maximum.');
        }

        return $this->ticketTypeRepository->update($ticketType, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $ticketType = $this->getById($tenantId, $id);

        if ($ticketType->quantity_sold > 0) {
            throw new RuntimeException('This ticket type has sales and cannot be deleted. Deactivate it instead.');
        }

        return $this->ticketTypeRepository->delete($ticketType);
    }
}
