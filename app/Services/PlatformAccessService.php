<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PlatformAccessService
{
    public function listUsers(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->with('role')
            ->where('is_platform_admin', true)
            ->whereNull('tenant_id');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }

        if ($roleId = ($filters['role_id'] ?? null)) {
            $query->where('role_id', (int) $roleId);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function createUser(array $data): User
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $role = $this->platformRoleOrNull($data['role_id'] ?? null);

        return User::query()->create([
            'tenant_id' => null,
            'role_id' => $role?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'is_platform_admin' => true,
        ]);
    }

    public function updateUser(int $id, array $data, User $actor): User
    {
        $user = $this->platformUserOrFail($id);
        $user->loadMissing('role');
        $wasActive = $user->status === 'active';
        $becomesSuspended = isset($data['status']) && $data['status'] !== 'active';

        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (User::query()->where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                throw new RuntimeException('An account with this email already exists.');
            }
        }

        if ($becomesSuspended && $actor->id === $user->id) {
            throw new RuntimeException('You cannot suspend your own platform administrator account.');
        }

        if ($wasActive && $becomesSuspended) {
            $this->assertAnotherActivePlatformAdminExists($user->id);
            $this->assertAnotherFullAccessPlatformAdminExists($user->id);
        }

        if (array_key_exists('role_id', $data)) {
            $nextRole = $this->platformRoleOrNull($data['role_id']);
            $currentlyFullAccess = $this->userHasFullAccess($user);
            $nextFullAccess = $nextRole === null || $this->roleHasFullAccess($nextRole);

            if ($currentlyFullAccess && ! $nextFullAccess) {
                $this->assertAnotherFullAccessPlatformAdminExists($user->id);
            }
        }

        $role = $this->platformRoleOrNull($data['role_id'] ?? null, keepExisting: true);

        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value) => $value !== null);

        if (array_key_exists('role_id', $data)) {
            $payload['role_id'] = $role?->id;
        }

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $user->tokens()->delete();
        }

        $user->update($payload);

        return $user->fresh(['role']);
    }

    public function deleteUser(int $id, User $actor): void
    {
        $user = $this->platformUserOrFail($id);
        $user->loadMissing('role');

        if ($actor->id === $user->id) {
            throw new RuntimeException('You cannot delete your own platform administrator account.');
        }

        $this->assertAnotherActivePlatformAdminExists($user->id);
        $this->assertAnotherFullAccessPlatformAdminExists($user->id);

        $user->tokens()->delete();
        $user->delete();
    }

    public function listRoles(): Collection
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions ?? [],
                'is_system' => (bool) $role->is_system,
                'users_count' => (int) $role->users_count,
                'created_at' => $role->created_at?->toIso8601String(),
            ]);
    }

    public function createRole(array $data): Role
    {
        $data['permissions'] = $this->normalizePermissions($data['permissions'] ?? []);

        if (Role::query()->whereNull('tenant_id')->where('slug', $data['slug'])->exists()) {
            throw new RuntimeException('A platform role with this slug already exists.');
        }

        return Role::query()->create([
            'tenant_id' => null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'],
            'is_system' => false,
        ]);
    }

    public function updateRole(int $id, array $data): Role
    {
        $role = $this->platformRoleOrFail($id);

        if ($role->is_system) {
            throw new RuntimeException('System platform roles cannot be modified.');
        }

        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn ($value) => $value !== null);

        if (array_key_exists('permissions', $data)) {
            $payload['permissions'] = $this->normalizePermissions($data['permissions']);
        }

        $role->update($payload);

        return $role->fresh();
    }

    public function deleteRole(int $id): void
    {
        $role = $this->platformRoleOrFail($id);

        if ($role->is_system) {
            throw new RuntimeException('System platform roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            throw new RuntimeException('This platform role is assigned to team members. Reassign them before deleting the role.');
        }

        $role->delete();
    }

    private function platformUserOrFail(int $id): User
    {
        $user = User::query()
            ->where('id', $id)
            ->where('is_platform_admin', true)
            ->whereNull('tenant_id')
            ->first();

        if ($user === null) {
            throw new RuntimeException('Platform administrator not found.');
        }

        return $user;
    }

    private function platformRoleOrFail(int $id): Role
    {
        $role = Role::query()
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->first();

        if ($role === null) {
            throw new RuntimeException('Platform role not found.');
        }

        return $role;
    }

    private function platformRoleOrNull(mixed $roleId, bool $keepExisting = false): ?Role
    {
        if ($roleId === null && $keepExisting) {
            return null;
        }

        if ($roleId === null || $roleId === '') {
            return null;
        }

        return $this->platformRoleOrFail((int) $roleId);
    }

    /**
     * @return array<string>
     */
    private function platformPermissionList(): array
    {
        return Permissions::platformPermissions();
    }

    private function userHasFullAccess(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        $role = $user->role;

        if ($role === null) {
            return true;
        }

        return $this->roleHasFullAccess($role);
    }

    private function roleHasFullAccess(Role $role): bool
    {
        if ($role->is_system && $role->slug === 'owner') {
            return true;
        }

        return array_diff($this->platformPermissionList(), $role->permissions ?? []) === [];
    }

    private function assertAnotherFullAccessPlatformAdminExists(?int $excludeId = null): void
    {
        $count = User::query()
            ->where('is_platform_admin', true)
            ->whereNull('tenant_id')
            ->where('status', 'active')
            ->with('role')
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get()
            ->filter(fn (User $user) => $this->userHasFullAccess($user))
            ->count();

        if ($count < 1) {
            throw new RuntimeException('At least one active platform administrator with full access must remain. Assign a full-access role or remove a restricted one before continuing.');
        }
    }

    private function assertAnotherActivePlatformAdminExists(?int $excludeId = null): void
    {
        $count = User::query()
            ->where('is_platform_admin', true)
            ->whereNull('tenant_id')
            ->where('status', 'active')
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->count();

        if ($count < 1) {
            throw new RuntimeException('At least one active platform administrator must remain. Activate or create another administrator first.');
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalizePermissions(array $permissions): array
    {
        $allowed = Permissions::platformPermissions();
        $given = collect($permissions)
            ->filter(fn ($permission) => is_string($permission))
            ->unique()
            ->values()
            ->all();

        $unknown = array_diff($given, $allowed);

        if ($unknown !== []) {
            throw new RuntimeException('Unknown platform permissions: '.implode(', ', $unknown));
        }

        if ($given === []) {
            throw new RuntimeException('Select at least one platform permission for the role.');
        }

        return $given;
    }
}
