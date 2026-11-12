<?php

use App\Http\Controllers\Api\OrderItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:orders.manage'])->group(function (): void {
    Route::apiResource('order-items', OrderItemController::class);
});
