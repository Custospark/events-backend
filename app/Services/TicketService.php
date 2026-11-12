<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Services\Contracts\TicketServiceInterface;
use App\Support\TicketQrCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketService implements TicketServiceInterface
{
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->ticketRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Ticket
    {
        $ticket = $this->ticketRepository->find($tenantId, $id);

        if ($ticket === null) {
            throw new RuntimeException('Ticket not found.');
        }

        return $ticket;
    }

    public function findByCode(int $tenantId, string $code): Ticket
    {
        $ticket = $this->ticketRepository->findByCode($tenantId, $this->resolveCode($code));

        if ($ticket === null) {
            throw new RuntimeException('Ticket not found.');
        }

        return $ticket;
    }

    public function generateForOrder(Order $order): Collection
    {
        if (! $order->isPaid()) {
            throw new RuntimeException('Tickets can only be issued for a paid order.');
        }

        $order->loadMissing('items');

        return DB::transaction(function () use ($order): Collection {
            $tickets = new Collection;

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $code = $this->uniqueCode();

                    $tickets->push($this->ticketRepository->create([
                        'tenant_id' => $order->tenant_id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'event_id' => $order->event_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'code' => $code,
                        'holder_name' => $order->buyer_name,
                        'holder_email' => $order->buyer_email,
                        'status' => 'valid',
                        'qr_payload' => TicketQrCode::make($code, (int) $order->event_id, (int) $order->tenant_id),
                    ]));
                }
            }

            return $tickets;
        });
    }

    public function checkIn(int $tenantId, string $code, int $userId): Ticket
    {
        $ticket = $this->findByCode($tenantId, $code);

        if ($ticket->status !== 'valid') {
            throw new RuntimeException('This ticket is not valid for check-in.');
        }

        if ($ticket->checked_in_at !== null) {
            throw new RuntimeException('This ticket has already been checked in.');
        }

        $ticket->update([
            'status' => 'used',
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);

        return $ticket->fresh();
    }

    public function verify(int $tenantId, string $codeOrToken): array
    {
        $ticket = $this->findByCode($tenantId, $codeOrToken);

        return $this->verificationResult($ticket);
    }

    public function verifyPublic(string $token): array
    {
        $payload = TicketQrCode::parse($token);

        if ($payload === null) {
            throw new RuntimeException('Invalid ticket QR code.');
        }

        $ticket = $this->findByCode((int) $payload['t'], (string) $payload['c']);

        return $this->verificationResult($ticket->load(['event', 'ticketType', 'tenant']));
    }

    public function qrToken(Ticket $ticket): string
    {
        $existing = TicketQrCode::parse((string) $ticket->qr_payload);

        if ($existing !== null
            && $existing['c'] === $ticket->code
            && $existing['t'] === (int) $ticket->tenant_id
        ) {
            return (string) $ticket->qr_payload;
        }

        $token = TicketQrCode::make($ticket->code, (int) $ticket->event_id, (int) $ticket->tenant_id);

        $ticket->update(['qr_payload' => $token]);

        return $token;
    }

    private function resolveCode(string $codeOrToken): string
    {
        $payload = TicketQrCode::parse($codeOrToken);

        return $payload !== null ? (string) $payload['c'] : $codeOrToken;
    }

    /**
     * @return array{valid: bool, reason: ?string, ticket: Ticket}
     */
    private function verificationResult(Ticket $ticket): array
    {
        $reason = null;

        if ($ticket->status !== 'valid') {
            $reason = 'This ticket is not valid for check-in.';
        } elseif ($ticket->checked_in_at !== null) {
            $reason = 'This ticket has already been checked in.';
        }

        return [
            'valid' => $reason === null,
            'reason' => $reason,
            'ticket' => $ticket,
        ];
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'TKT-'.strtoupper(Str::random(12));
        } while (Ticket::query()->where('code', $code)->exists());

        return $code;
    }
}
