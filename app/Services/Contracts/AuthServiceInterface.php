<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(array $credentials): array;

    public function register(array $data): User;

    public function logout(User $user): void;
}
