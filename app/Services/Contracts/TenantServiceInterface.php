<?php

namespace App\Services\Contracts;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantServiceInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $id): Tenant;

    public function getBySlug(string $slug): Tenant;

    public function onboard(array $data): Tenant;

    public function update(int $id, array $data, ?User $actor = null): Tenant;

    public function delete(int $id): bool;

    public function suspend(int $id, string $reason, ?User $actor = null): Tenant;
}
