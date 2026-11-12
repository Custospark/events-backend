<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected TenantRepositoryInterface $tenantRepository,
        protected RoleRepositoryInterface $roleRepository,
    ) {}

    public function login(array $credentials): array
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw new RuntimeException('Invalid email or password.');
        }

        if ($user->status !== 'active') {
            throw new RuntimeException('This account is not active.');
        }

        if ($user->tenant_id !== null) {
            $tenant = $this->tenantRepository->find((int) $user->tenant_id);

            if ($tenant === null || ! $tenant->isActive()) {
                throw new RuntimeException('This tenant account is not active.');
            }
        }

        $user->update(['last_login_at' => now()]);

        return [
            'user' => $user->fresh(['tenant', 'role']),
            'token' => $user->createToken('web')->plainTextToken,
        ];
    }

    public function register(array $data): User
    {
        $tenant = $this->tenantRepository->findBySlug($data['tenant_slug']);

        if ($tenant === null || ! $tenant->isActive()) {
            throw new RuntimeException('This tenant is not accepting registrations.');
        }

        if ($this->userRepository->findByEmail($data['email']) !== null) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $attendeeRole = $this->roleRepository->findBySlug((int) $tenant->id, 'attendee');

        return $this->userRepository->create([
            'tenant_id' => $tenant->id,
            'role_id' => $attendeeRole?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
            'is_platform_admin' => false,
        ]);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
