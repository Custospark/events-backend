<?php

namespace App\Repositories\Contracts;

use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderItemRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $tenantId, int $id): ?OrderItem;

    public function create(array $data): OrderItem;

    public function update(OrderItem $orderItem, array $data): OrderItem;

    public function delete(OrderItem $orderItem): bool;
}
