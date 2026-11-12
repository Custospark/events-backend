<?php

namespace App\Services\Contracts;

use App\Models\Payout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayoutServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Payout;

    public function settle(int $tenantId, int $eventId): Payout;
}
