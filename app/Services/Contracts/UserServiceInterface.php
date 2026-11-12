<?php

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): User;

    public function invite(int $tenantId, array $data): User;

    public function update(int $tenantId, int $id, array $data): User;

    public function delete(int $tenantId, int $id): bool;
}
