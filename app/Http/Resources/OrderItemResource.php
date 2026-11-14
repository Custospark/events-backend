<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->order_id,
            'ticket_type_id' => $this->ticket_type_id,
            'quantity' => $this->quantity,
            'admits' => $this->admits,
            'unit_price' => $this->unit_price,
            'processing_fee' => $this->processing_fee,
            'line_total' => $this->line_total,
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
        ];
    }
}
