<?php

use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:roles.manage'])->group(function (): void {
    Route::apiResource('roles', RoleController::class);
});
