<?php

namespace App\Services\Contracts;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoleServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Role;

    public function create(int $tenantId, array $data): Role;

    public function update(int $tenantId, int $id, array $data): Role;

    public function delete(int $tenantId, int $id): bool;
}
