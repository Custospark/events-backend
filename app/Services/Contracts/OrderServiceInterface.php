<?php

namespace App\Services\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Order;

    public function getByReference(int $tenantId, string $reference): Order;

    public function getPublicByReference(string $reference): Order;

    public function create(int $tenantId, array $data): Order;

    public function cancel(int $tenantId, int $id, string $reason = ''): Order;

    public function markPaid(Order $order, string $providerReference): Order;

    public function refund(int $tenantId, int $id): Order;

    public function expirePendingOrders(): int;
}
