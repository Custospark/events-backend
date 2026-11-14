<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_by' => $this->uploaded_by,
            'download_url' => url('/api/v1/payouts/'.$this->payout_id.'/attachments/'.$this->id.'/download'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
