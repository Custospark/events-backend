<?php

namespace App\Repositories\Eloquent;

use App\Models\Payout;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayoutRepository implements PayoutRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payout::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('reference', 'like', "%{$search}%"))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Payout
    {
        return Payout::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): Payout
    {
        return Payout::query()->create($data);
    }

    public function update(Payout $payout, array $data): Payout
    {
        $payout->update($data);

        return $payout->fresh();
    }

    public function delete(Payout $payout): bool
    {
        return (bool) $payout->delete();
    }
}
