<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\QrCodeService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketIssuedEmail extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        $eventTitle = $this->order->event?->title ?? 'your event';

        return new Envelope(
            subject: "Your tickets for {$eventTitle}",
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['event.tenant', 'items.ticketType', 'tickets.ticketType']);

        $event = $this->order->event;
        $qrCode = app(QrCodeService::class);

        $tickets = $this->order->tickets
            ->map(function (Ticket $ticket) use ($qrCode): array {
                $type = $ticket->ticketType?->name
                    ?? $this->order->items
                        ->firstWhere('ticket_type_id', $ticket->ticket_type_id)
                        ?->ticketType
                        ?->name
                    ?? 'General admission';

                return [
                    'code' => $ticket->code,
                    'holder' => $ticket->holder_name,
                    'type' => $type,
                    'status' => strtoupper($ticket->status),
                    'qrPng' => $qrCode->png($ticket->qr_payload ?: $ticket->code),
                ];
            })
            ->all();

        return new Content(
            view: 'emails.tickets',
            with: [
                'title' => 'Your tickets are ready',
                'buyerName' => $this->order->buyer_name,
                'eventTitle' => $event?->title ?? 'Event ticket',
                'eventDate' => $this->formatEventRange($event),
                'eventVenue' => $this->formatVenue($event),
                'tenantName' => $event?->tenant?->name ?? config('brand.name'),
                'orderReference' => $this->order->reference,
                'total' => $this->formatMoney(),
                'orderUrl' => $this->orderUrl(),
                'supportEmail' => config('brand.company_email', 'events@custospark.com'),
                'tickets' => $tickets,
            ],
        );
    }

    private function formatEventRange(?Event $event): string
    {
        if ($event?->starts_at === null) {
            return 'Date to be announced';
        }

        $timezone = $event->timezone ?: 'Africa/Kampala';
        $start = $event->starts_at->copy()->setTimezone($timezone);
        $end = $event->ends_at?->copy()->setTimezone($timezone);

        if ($end === null) {
            return $start->format('D, M j, Y \a\t g:i A');
        }

        if ($start->isSameDay($end)) {
            return $start->format('D, M j, Y').' from '.$start->format('g:i A').' to '.$end->format('g:i A');
        }

        return $start->format('D, M j, Y g:i A').' to '.$end->format('D, M j, Y g:i A');
    }

    private function formatVenue(?Event $event): string
    {
        $parts = array_filter([
            $event?->venue,
            $event?->address,
            $event?->city,
            $event?->country,
        ]);

        return $parts === [] ? 'Venue to be announced' : implode(', ', $parts);
    }

    private function formatMoney(): string
    {
        $zeroDecimal = in_array(strtoupper((string) $this->order->currency), [
            'UGX', 'TZS', 'RWF', 'KMF', 'DJF', 'VND',
        ], true);

        return number_format(
            (float) $this->order->total,
            $zeroDecimal ? 0 : 2,
        ).' '.$this->order->currency;
    }

    private function orderUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/orders/'.$this->order->reference;
    }
}
