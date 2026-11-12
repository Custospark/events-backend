<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleRepository implements RoleRepositoryInterface
{
    public function all(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $id): ?Role
    {
        return Role::query()->where('tenant_id', $tenantId)->find($id);
    }

    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->fresh();
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }

    public function findBySlug(int $tenantId, string $slug): ?Role
    {
        return Role::query()->where('tenant_id', $tenantId)->where('slug', $slug)->first();
    }
}
