<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): User
    {
        $user = $this->userRepository->find($tenantId, $id);

        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }

    public function invite(int $tenantId, array $data): User
    {
        if ($this->userRepository->findByEmail($data['email']) !== null) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $data['tenant_id'] = $tenantId;
        $data['status'] = $data['status'] ?? 'active';
        $data['is_platform_admin'] = false;

        return $this->userRepository->create($data);
    }

    public function update(int $tenantId, int $id, array $data): User
    {
        $user = $this->getById($tenantId, $id);
        unset($data['tenant_id'], $data['is_platform_admin']);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existing = $this->userRepository->findByEmail($data['email']);

            if ($existing !== null) {
                throw new RuntimeException('An account with this email already exists.');
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->userRepository->delete($this->getById($tenantId, $id));
    }
}
