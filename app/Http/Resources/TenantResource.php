<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'domain' => $this->domain,
            'logo_path' => $this->logo_path,
            'status' => $this->status,
            'commission_percent' => $this->commission_percent,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'settings' => $this->settings,
            'public_url' => rtrim((string) config('app.frontend_url'), '/').'/t/'.$this->slug,
            'qr_code_url' => url('/api/v1/public/tenants/'.$this->slug.'/qr'),
            'onboarded_at' => $this->onboarded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
