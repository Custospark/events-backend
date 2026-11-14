<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $internal = $request->is('api/v1/payments*', 'api/v1/orders*');

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'order_id' => $this->order_id,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'organiser_name' => $this->organiser_name,
            'event_title' => $this->event_title,
            'transaction_label' => $this->transaction_label,
            'fee_bearer_code' => $internal ? $this->fee_bearer_code : null,
            'reconciliation_code' => $internal ? $this->reconciliation_code : null,
            'reconciliation' => $internal ? $this->reconciliation : null,
            'payload' => $this->payload,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
