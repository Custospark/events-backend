<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function discover(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $tenantId, int $id): ?Event;

    public function findBySlug(int $tenantId, string $slug): ?Event;

    public function create(array $data): Event;

    public function update(Event $event, array $data): Event;

    public function delete(Event $event): bool;
}
