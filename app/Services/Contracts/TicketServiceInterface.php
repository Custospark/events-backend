<?php

namespace App\Services\Contracts;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TicketServiceInterface
{
    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getById(int $tenantId, int $id): Ticket;

    public function findByCode(int $tenantId, string $code): Ticket;

    public function generateForOrder(Order $order): Collection;

    public function checkIn(int $tenantId, string $code, int $userId): Ticket;

    /**
     * @return array{valid: bool, reason: ?string, ticket: Ticket}
     */
    public function verify(int $tenantId, string $codeOrToken): array;

    /**
     * @return array{valid: bool, reason: ?string, ticket: Ticket}
     */
    public function verifyPublic(string $token): array;

    public function qrToken(Ticket $ticket): string;
}
