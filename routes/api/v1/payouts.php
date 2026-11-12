<?php

use App\Http\Controllers\Api\PayoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:payouts.manage'])->group(function (): void {
    Route::post('payouts/settle', [PayoutController::class, 'settle']);
    Route::apiResource('payouts', PayoutController::class)->only(['index', 'show']);
});
