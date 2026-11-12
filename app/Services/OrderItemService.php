<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Repositories\Contracts\OrderItemRepositoryInterface;
use App\Services\Contracts\OrderItemServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class OrderItemService implements OrderItemServiceInterface
{
    public function __construct(
        protected OrderItemRepositoryInterface $orderItemRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderItemRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): OrderItem
    {
        $orderItem = $this->orderItemRepository->find($tenantId, $id);

        if ($orderItem === null) {
            throw new RuntimeException('Order item not found.');
        }

        return $orderItem;
    }

    public function create(int $tenantId, array $data): OrderItem
    {
        $data['tenant_id'] = $tenantId;

        return $this->orderItemRepository->create($data);
    }

    public function update(int $tenantId, int $id, array $data): OrderItem
    {
        $orderItem = $this->getById($tenantId, $id);
        unset($data['tenant_id']);

        return $this->orderItemRepository->update($orderItem, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $orderItem = $this->getById($tenantId, $id);

        return $this->orderItemRepository->delete($orderItem);
    }
}
