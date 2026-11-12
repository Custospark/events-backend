<?php

namespace App\Services\Contracts;

interface DashboardServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function summary(int $tenantId): array;
}
