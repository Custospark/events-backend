<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\TenantServiceInterface;
use App\Services\Contracts\TicketServiceInterface;
use App\Services\QrCodeService;
use App\Support\TenantContext;
use App\Support\TicketQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

class PublicQrController extends Controller
{
    public function __construct(
        protected TenantServiceInterface $tenantService,
        protected TicketServiceInterface $ticketService,
        protected QrCodeService $qrCode,
        protected TenantContext $tenantContext,
    ) {}

    public function tenant(string $tenantSlug): Response
    {
        $tenant = $this->tenantService->getBySlug($tenantSlug);

        if (! $tenant->isActive()) {
            abort(404, 'Tenant not found.');
        }

        return response($this->qrCode->svg($this->tenantPublicUrl($tenantSlug)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="tenant-'.$tenantSlug.'.svg"',
        ]);
    }

    public function ticket(string $token): Response
    {
        $this->tenantContext->clear();

        $payload = TicketQrCode::parse($token);

        if ($payload === null) {
            abort(404, 'Ticket not found.');
        }

        try {
            $ticket = $this->ticketService->findByCode((int) $payload['t'], (string) $payload['c']);
        } catch (RuntimeException) {
            abort(404, 'Ticket not found.');
        }

        return response($this->qrCode->svg($this->ticketService->qrToken($ticket)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="ticket-'.$ticket->code.'.svg"',
        ]);
    }

    public function verifyTicket(string $token): JsonResponse
    {
        $this->tenantContext->clear();

        try {
            $result = $this->ticketService->verifyPublic($token);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $ticket = $result['ticket'];

        return response()->json([
            'data' => [
                'valid' => $result['valid'],
                'reason' => $result['reason'],
                'ticket' => [
                    'code' => $ticket->code,
                    'status' => $ticket->status,
                    'holder_name' => $this->maskName((string) $ticket->holder_name),
                    'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                    'event' => [
                        'title' => $ticket->event?->title,
                        'starts_at' => $ticket->event?->starts_at?->toIso8601String(),
                        'venue' => $ticket->event?->venue,
                    ],
                    'ticket_type' => $ticket->ticketType?->name,
                    'tenant' => $ticket->tenant?->name,
                ],
            ],
        ]);
    }

    private function tenantPublicUrl(string $tenantSlug): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/t/'.$tenantSlug;
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        $masked = array_map(function (string $part): string {
            if (strlen($part) <= 2) {
                return str_repeat('*', strlen($part));
            }

            return mb_substr($part, 0, 1).str_repeat('*', max(1, mb_strlen($part) - 2)).mb_substr($part, -1);
        }, $parts);

        return implode(' ', $masked);
    }
}
