<?php

namespace App\Services\Contracts;

use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TicketTypeServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getForEvent(int $tenantId, int $eventId): Collection;

    public function getById(int $tenantId, int $id): TicketType;

    public function create(int $tenantId, array $data): TicketType;

    public function update(int $tenantId, int $id, array $data): TicketType;

    public function delete(int $tenantId, int $id): bool;
}
