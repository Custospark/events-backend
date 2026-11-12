<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();
        $user = $request->user();

        if ($tenant === null) {
            return response()->json([
                'message' => 'Tenant could not be resolved for this request.',
                'errors' => ['tenant' => ['Provide a valid tenant or X-Tenant header.']],
            ], 404);
        }

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_platform_admin && (int) $user->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'message' => 'You do not have access to this tenant.',
                'errors' => ['tenant' => ['Cross-tenant access is not allowed.']],
            ], 403);
        }

        return $next($request);
    }
}
