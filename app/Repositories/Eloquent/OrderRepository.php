<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->with('event')
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('reference', 'like', "%{$search}%"))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Order
    {
        return Order::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): Order
    {
        return Order::query()->create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh();
    }

    public function delete(Order $order): bool
    {
        return (bool) $order->delete();
    }

    public function findByReference(string $reference): ?Order
    {
        return Order::query()->where('reference', $reference)->first();
    }

    public function expirePending(): int
    {
        return Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired', 'cancelled_at' => now()]);
    }

    public function expiredPending(): Collection
    {
        return Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('items')
            ->get();
    }
}
