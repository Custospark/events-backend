<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('provider_reference', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Payment
    {
        return Payment::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): Payment
    {
        return Payment::query()->create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment->fresh();
    }

    public function delete(Payment $payment): bool
    {
        return (bool) $payment->delete();
    }

    public function findByProviderReference(string $reference): ?Payment
    {
        return Payment::query()->where('provider_reference', $reference)->first();
    }

    public function findByOrderId(int $orderId): ?Payment
    {
        return Payment::query()->where('order_id', $orderId)->latest()->first();
    }

    public function pendingForReconciliation(int $limit = 50): Collection
    {
        return Payment::query()
            ->where('status', 'pending')
            ->whereNotNull('provider_reference')
            ->oldest()
            ->limit($limit)
            ->get();
    }
}
