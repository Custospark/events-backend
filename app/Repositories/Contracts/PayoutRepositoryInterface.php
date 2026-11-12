<?php

namespace App\Repositories\Contracts;

use App\Models\Payout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayoutRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $tenantId, int $id): ?Payout;

    public function create(array $data): Payout;

    public function update(Payout $payout, array $data): Payout;

    public function delete(Payout $payout): bool;
}
