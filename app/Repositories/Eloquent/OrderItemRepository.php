<?php

namespace App\Repositories\Eloquent;

use App\Models\OrderItem;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderItemRepository implements OrderItemRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return OrderItem::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('id', 'like', "%{$search}%"))
            ->when($filters['order_id'] ?? null, fn ($q, $orderId) => $q->where('order_id', $orderId))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?OrderItem
    {
        return OrderItem::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): OrderItem
    {
        return OrderItem::query()->create($data);
    }

    public function update(OrderItem $orderItem, array $data): OrderItem
    {
        $orderItem->update($data);

        return $orderItem->fresh();
    }

    public function delete(OrderItem $orderItem): bool
    {
        return (bool) $orderItem->delete();
    }
}
