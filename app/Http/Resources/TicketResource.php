<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'event_id' => $this->event_id,
            'ticket_type_id' => $this->ticket_type_id,
            'code' => $this->code,
            'holder_name' => $this->holder_name,
            'holder_email' => $this->holder_email,
            'status' => $this->status,
            'qr_payload' => $this->qr_payload,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_in_by' => $this->checked_in_by,
            'metadata' => $this->metadata,
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'event' => new EventResource($this->whenLoaded('event')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
