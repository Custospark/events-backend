<?php

namespace App\Repositories\Eloquent;

use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketRepository implements TicketRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('code', 'like', "%{$search}%"))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Ticket
    {
        return Ticket::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): Ticket
    {
        return Ticket::query()->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket->fresh();
    }

    public function delete(Ticket $ticket): bool
    {
        return (bool) $ticket->delete();
    }

    public function findByCode(int $tenantId, string $code): ?Ticket
    {
        return Ticket::query()->where('tenant_id', $tenantId)->where('code', $code)->first();
    }
}
