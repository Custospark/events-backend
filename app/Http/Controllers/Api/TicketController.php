<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CheckInRequest;
use App\Http\Requests\VerifyTicketRequest;
use App\Http\Resources\TicketCollection;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Contracts\TicketServiceInterface;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends ApiController
{
    public function __construct(
        protected TicketServiceInterface $ticketService,
    ) {}

    public function index(Request $request): TicketCollection
    {
        return new TicketCollection(
            $this->ticketService->getAll(
                $this->tenantId($request),
                $request->only(['search', 'status', 'event_id']),
                $this->perPage($request),
            ),
        );
    }

    public function show(Request $request, int $ticket): TicketResource
    {
        return new TicketResource(
            $this->ticketService->getById($this->tenantId($request), $ticket)
                ->load(['event', 'ticketType']),
        );
    }

    public function findByCode(Request $request, string $code): TicketResource
    {
        return new TicketResource(
            $this->ticketService->findByCode($this->tenantId($request), $code)
                ->load(['event', 'ticketType']),
        );
    }

    public function verify(VerifyTicketRequest $request): JsonResponse
    {
        $result = $this->ticketService->verify(
            $this->tenantId($request),
            (string) ($request->validated()['token'] ?? $request->validated()['code']),
        );

        return $this->verificationResponse($result);
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->checkIn(
            $this->tenantId($request),
            (string) ($request->validated()['token'] ?? $request->validated()['code']),
            (int) $request->user()->id,
        );

        return $this->verificationResponse([
            'valid' => true,
            'reason' => null,
            'ticket' => $ticket->load(['event', 'ticketType']),
        ]);
    }

    public function qr(Request $request, int $ticket, QrCodeService $qrCode): Response
    {
        $ticket = $this->ticketService->getById($this->tenantId($request), $ticket);
        $token = $this->ticketService->qrToken($ticket);

        return response($qrCode->svg($token), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="ticket-'.$ticket->code.'.svg"',
        ]);
    }

    /**
     * @param  array{valid: bool, reason: ?string, ticket: Ticket}  $result
     */
    private function verificationResponse(array $result): JsonResponse
    {
        return response()->json([
            'data' => [
                'valid' => $result['valid'],
                'reason' => $result['reason'],
                'ticket' => new TicketResource($result['ticket']),
            ],
        ]);
    }
}
