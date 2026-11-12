<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:orders.manage'])->group(function (): void {
    Route::post('orders/expire-pending', [OrderController::class, 'expirePending']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order}/refund', [OrderController::class, 'refund']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
});
