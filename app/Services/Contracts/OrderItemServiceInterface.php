<?php

namespace App\Services\Contracts;

use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderItemServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): OrderItem;

    public function create(int $tenantId, array $data): OrderItem;

    public function update(int $tenantId, int $id, array $data): OrderItem;

    public function delete(int $tenantId, int $id): bool;
}
