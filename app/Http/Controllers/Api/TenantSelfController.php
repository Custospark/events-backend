<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\TenantCurrencyRequest;
use App\Http\Resources\TenantResource;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class TenantSelfController extends ApiController
{
    public function __construct(
        protected TenantServiceInterface $tenantService,
        protected TenantContext $tenantContext,
    ) {}

    public function updateCurrency(TenantCurrencyRequest $request): TenantResource|JsonResponse
    {
        $tenant = $this->tenantContext->get();

        if ($tenant === null) {
            return response()->json([
                'message' => 'Tenant could not be resolved.',
                'errors' => ['tenant' => ['Provide a valid tenant or X-Tenant header.']],
            ], 404);
        }

        $tenant = $this->tenantService->update((int) $tenant->id, [
            'currency' => $request->validated()['currency'],
        ]);

        return new TenantResource($tenant);
    }
}
