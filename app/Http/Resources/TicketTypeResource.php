<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenantCurrency = $this->tenant?->currency ?? $this->currency;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $tenantCurrency,
            'quantity_total' => $this->quantity_total,
            'quantity_sold' => $this->quantity_sold,
            'admits' => $this->admits,
            'remaining' => $this->remaining(),
            'min_per_order' => $this->min_per_order,
            'max_per_order' => $this->max_per_order,
            'sales_start_at' => $this->sales_start_at?->toIso8601String(),
            'sales_end_at' => $this->sales_end_at?->toIso8601String(),
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
