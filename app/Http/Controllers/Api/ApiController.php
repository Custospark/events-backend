<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function tenantId(Request $request): int
    {
        $tenantId = app(TenantContext::class)->id() ?? $request->user()?->tenant_id;

        if ($tenantId === null) {
            abort(400, 'Tenant context is required.');
        }

        return (int) $tenantId;
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 15)));
    }
}
