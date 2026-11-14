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
            'initiation' => $this->initiation,
            'request_priority' => $this->request_priority,
            'requested_note' => $this->requested_note,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'processing_started_at' => $this->processing_started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'decline_reason' => $this->decline_reason,
            'provider_reference' => $this->provider_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'attachments' => PayoutAttachmentResource::collection($this->whenLoaded('attachments')),
            'event' => new EventResource($this->whenLoaded('event')),
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => (int) $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
                'currency' => $this->tenant->currency,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => [
                'id' => (int) $this->requestedBy->id,
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
