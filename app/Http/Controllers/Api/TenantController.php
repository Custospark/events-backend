<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\TenantOnboardRequest;
use App\Http\Requests\TenantRequest;
use App\Http\Resources\TenantCollection;
use App\Http\Resources\TenantResource;
use App\Services\Contracts\TenantServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends ApiController
{
    public function __construct(
        protected TenantServiceInterface $tenantService,
    ) {}

    public function index(Request $request): TenantCollection
    {
        return new TenantCollection(
            $this->tenantService->getAll(
                $request->only(['search', 'status']),
                $this->perPage($request),
            ),
        );
    }

    public function store(TenantOnboardRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->onboard($request->validated());

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    public function show(int $tenant): TenantResource
    {
        return new TenantResource($this->tenantService->getById($tenant));
    }

    public function update(TenantRequest $request, int $tenant): TenantResource
    {
        return new TenantResource(
            $this->tenantService->update($tenant, $request->validated()),
        );
    }

    public function destroy(int $tenant): JsonResponse
    {
        $this->tenantService->delete($tenant);

        return response()->json(null, 204);
    }

    public function suspend(Request $request, int $tenant): TenantResource
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return new TenantResource(
            $this->tenantService->suspend($tenant, $validated['reason']),
        );
    }
}
