<?php

namespace App\Services\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function discover(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Event;

    public function getBySlug(int $tenantId, string $slug): Event;

    public function create(int $tenantId, array $data): Event;

    public function update(int $tenantId, int $id, array $data): Event;

    public function publish(int $tenantId, int $id): Event;

    public function unpublish(int $tenantId, int $id): Event;

    public function delete(int $tenantId, int $id): bool;
}
