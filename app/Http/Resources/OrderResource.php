<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'reference' => $this->reference,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'buyer_phone' => $this->buyer_phone,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'processing_fee' => $this->processing_fee,
            'buyer_processing_fee' => $this->buyer_processing_fee,
            'processing_fee_bearer' => $this->processing_fee_bearer,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'event' => new EventResource($this->whenLoaded('event')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
