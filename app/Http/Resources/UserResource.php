<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'role_id' => $this->role_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'is_platform_admin' => $this->is_platform_admin,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'role' => new RoleResource($this->whenLoaded('role')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
