<?php

use App\Http\Controllers\Api\TenantSelfController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:api',
    'auth:sanctum',
    'tenant.resolve',
    'tenant.access',
    'permission:settings.manage',
])->group(function (): void {
    Route::patch('tenant/currency', [TenantSelfController::class, 'updateCurrency']);
});
