<?php

namespace App\Repositories\Eloquent;

use App\Models\Event;
use App\Repositories\Contracts\EventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventRepository implements EventRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function discover(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Event::query()
            ->with([
                'tenant',
                'ticketTypes' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order'),
            ])
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->where('status', 'published')
            ->when($filters['search'] ?? null, function ($q, $search): void {
                $q->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($filters['upcoming'] ?? true, fn ($q) => $q->where('starts_at', '>=', now()))
            ->orderBy('starts_at')
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Event
    {
        return Event::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function findBySlug(int $tenantId, string $slug): ?Event
    {
        return Event::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();
    }

    public function create(array $data): Event
    {
        return Event::query()->create($data);
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);

        return $event->fresh();
    }

    public function delete(Event $event): bool
    {
        return (bool) $event->delete();
    }
}
