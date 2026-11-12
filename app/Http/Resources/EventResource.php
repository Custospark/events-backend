<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'status' => $this->status,
            'banner_path' => $this->banner_path,
            'capacity' => $this->capacity,
            'currency' => $this->currency,
            'service_fee_percent' => $this->service_fee_percent,
            'published_at' => $this->published_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
