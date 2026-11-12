<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\Permissions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantService implements TenantServiceInterface
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository,
        protected RoleRepositoryInterface $roleRepository,
        protected UserRepositoryInterface $userRepository,
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->tenantRepository->all($filters, $perPage);
    }

    public function getById(int $id): Tenant
    {
        $tenant = $this->tenantRepository->find($id);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        return $tenant;
    }

    public function getBySlug(string $slug): Tenant
    {
        $tenant = $this->tenantRepository->findBySlug($slug);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        return $tenant;
    }

    public function onboard(array $data): Tenant
    {
        if ($this->userRepository->findByEmail($data['admin_email']) !== null) {
            throw new RuntimeException('An account with the administrator email already exists.');
        }

        return DB::transaction(function () use ($data): Tenant {
            $tenant = $this->tenantRepository->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'domain' => $data['domain'] ?? null,
                'commission_percent' => $data['commission_percent'] ?? config('custospark.commission_percent', 5),
                'currency' => $data['currency'] ?? 'UGX',
                'timezone' => $data['timezone'] ?? 'Africa/Kampala',
                'status' => 'active',
                'settings' => $data['settings'] ?? null,
                'onboarded_at' => now(),
            ]);

            $adminRole = null;

            foreach (Permissions::defaultRoles() as $slug => $role) {
                $created = $this->roleRepository->create([
                    'tenant_id' => $tenant->id,
                    'name' => $role['name'],
                    'slug' => $slug,
                    'description' => "System {$role['name']} role.",
                    'permissions' => $role['permissions'],
                    'is_system' => true,
                ]);

                if ($slug === 'admin') {
                    $adminRole = $created;
                }
            }

            $this->userRepository->create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole?->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'password' => $data['admin_password'],
                'status' => 'active',
                'is_platform_admin' => false,
            ]);

            return $tenant->fresh();
        });
    }

    public function update(int $id, array $data): Tenant
    {
        $tenant = $this->getById($id);
        unset($data['onboarded_at']);

        return $this->tenantRepository->update($tenant, $data);
    }

    public function delete(int $id): bool
    {
        return $this->tenantRepository->delete($this->getById($id));
    }

    public function suspend(int $id, string $reason): Tenant
    {
        $tenant = $this->getById($id);
        $settings = $tenant->settings ?? [];
        $settings['suspension_reason'] = $reason;
        $settings['suspended_at'] = now()->toIso8601String();

        return $this->tenantRepository->update($tenant, [
            'status' => 'suspended',
            'settings' => $settings,
        ]);
    }
}
