<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:reports.view'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index']);
});
