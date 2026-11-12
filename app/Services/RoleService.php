<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class RoleService implements RoleServiceInterface
{
    public function __construct(
        protected RoleRepositoryInterface $roleRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->roleRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Role
    {
        $role = $this->roleRepository->find($tenantId, $id);

        if ($role === null) {
            throw new RuntimeException('Role not found.');
        }

        return $role;
    }

    public function create(int $tenantId, array $data): Role
    {
        $data['tenant_id'] = $tenantId;

        return $this->roleRepository->create($data);
    }

    public function update(int $tenantId, int $id, array $data): Role
    {
        $role = $this->getById($tenantId, $id);
        unset($data['tenant_id']);

        return $this->roleRepository->update($role, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $role = $this->getById($tenantId, $id);

        return $this->roleRepository->delete($role);
    }
}
