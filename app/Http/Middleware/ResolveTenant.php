<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        $tenant = $this->fromAuthenticatedUser($request)
            ?? $this->fromRoute($request)
            ?? $this->fromHeader($request)
            ?? $this->fromHost($request);

        if ($tenant !== null && $tenant->status === 'active') {
            $this->context->set($tenant);
        }

        return $next($request);
    }

    private function fromAuthenticatedUser(Request $request): ?Tenant
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        return Tenant::query()->find($user->tenant_id);
    }

    private function fromRoute(Request $request): ?Tenant
    {
        $slug = $request->route('tenantSlug') ?? $request->route('tenant');

        if (is_object($slug) && $slug instanceof Tenant) {
            return $slug;
        }

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Tenant::query()
            ->where('slug', $slug)
            ->orWhere('domain', $slug)
            ->first();
    }

    private function fromHeader(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Tenant::query()
            ->where('slug', $slug)
            ->orWhere('domain', $slug)
            ->first();
    }

    private function fromHost(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());
        $suffix = strtolower((string) config('tenancy.domain_suffix'));

        $tenant = Tenant::query()->where('domain', $host)->first();

        if ($tenant !== null || $suffix === '') {
            return $tenant;
        }

        if (str_ends_with($host, '.'.$suffix)) {
            $slug = substr($host, 0, -1 * (strlen($suffix) + 1));

            return Tenant::query()->where('slug', $slug)->first();
        }

        return null;
    }
}
