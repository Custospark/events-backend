<?php

namespace App\Repositories\Eloquent;

use App\Models\TicketType;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TicketTypeRepository implements TicketTypeRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?TicketType
    {
        return TicketType::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): TicketType
    {
        return TicketType::query()->create($data);
    }

    public function update(TicketType $ticketType, array $data): TicketType
    {
        $ticketType->update($data);

        return $ticketType->fresh();
    }

    public function delete(TicketType $ticketType): bool
    {
        return (bool) $ticketType->delete();
    }

    public function allForEvent(int $tenantId, int $eventId): Collection
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('sort_order')
            ->get();
    }

    public function lockForUpdate(int $tenantId, int $id): ?TicketType
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->find($id);
    }
}
