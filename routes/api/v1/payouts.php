<?php

use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\TenantPayoutAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:payouts.manage'])->group(function (): void {
    Route::get('payout-account', [TenantPayoutAccountController::class, 'show']);
    Route::patch('payout-account', [TenantPayoutAccountController::class, 'update']);
    Route::post('payouts/settle', [PayoutController::class, 'settle']);
    Route::apiResource('payouts', PayoutController::class)->only(['index', 'show']);
});
