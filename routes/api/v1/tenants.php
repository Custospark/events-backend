<?php

use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'platform'])->group(function (): void {
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
    Route::apiResource('tenants', TenantController::class);
});
