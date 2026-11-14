<?php

namespace App\Services\Contracts;

use App\Models\Payout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayoutServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Payout;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutstanding(int $tenantId): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(int $tenantId, array $data): Payout;
}
