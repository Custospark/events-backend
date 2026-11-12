<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PaymentRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $tenantId, int $id): ?Payment;

    public function create(array $data): Payment;

    public function update(Payment $payment, array $data): Payment;

    public function delete(Payment $payment): bool;

    public function findByProviderReference(string $reference): ?Payment;

    public function findByOrderId(int $orderId): ?Payment;

    public function pendingForReconciliation(int $limit = 50): Collection;
}
