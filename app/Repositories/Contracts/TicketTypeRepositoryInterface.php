<?php

namespace App\Repositories\Contracts;

use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TicketTypeRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $tenantId, int $id): ?TicketType;

    public function create(array $data): TicketType;

    public function update(TicketType $ticketType, array $data): TicketType;

    public function delete(TicketType $ticketType): bool;

    public function allForEvent(int $tenantId, int $eventId): Collection;

    public function lockForUpdate(int $tenantId, int $id): ?TicketType;
}
