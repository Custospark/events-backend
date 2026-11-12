<?php

namespace App\Services\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Payment;

    public function handleWebhook(string $rawPayload, ?string $signature, array $payload): Payment;

    public function verify(int $tenantId, int $id): Payment;

    public function verifyByReference(string $reference): Payment;

    public function refund(int $tenantId, int $id): Payment;

    public function markRefunded(int $tenantId, int $id): Payment;

    /**
     * @return array{checked: int, succeeded: int, failed: int, pending: int, review: int, errors: int}
     */
    public function reconcilePending(int $limit = 50): array;
}
