<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\TenantPayoutAccountRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\TenantContext;

class TenantPayoutAccountController extends ApiController
{
    public function __construct(
        protected TenantServiceInterface $tenantService,
    ) {}

    public function show(): TenantResource
    {
        return new TenantResource($this->tenant());
    }

    public function update(TenantPayoutAccountRequest $request): TenantResource
    {
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];
        $account = $request->validated()['payout_account'] ?? null;

        if ($account === null || $account === []) {
            unset($settings['payout_account']);
        } else {
            $settings['payout_account'] = $account;
        }

        return new TenantResource(
            $this->tenantService->update($tenant->id, ['settings' => $settings]),
        );
    }

    private function tenant(): Tenant
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant === null) {
            abort(404, 'Tenant could not be resolved for this request.');
        }

        return $tenant;
    }
}
