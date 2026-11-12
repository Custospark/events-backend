<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'reference' => $this->reference,
            'gross_amount' => $this->gross_amount,
            'commission_amount' => $this->commission_amount,
            'net_amount' => $this->net_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'event' => new EventResource($this->whenLoaded('event')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
