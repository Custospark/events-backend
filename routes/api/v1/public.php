<?php

use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\PublicQrController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('events', [PublicController::class, 'discoverEvents'])->middleware('throttle:public');
    Route::get('orders/{reference}', [PublicController::class, 'publicOrderStatus'])->middleware('throttle:public');
    Route::post('orders/{reference}/verify', [PublicController::class, 'verifyPublicOrder'])->middleware('throttle:public');
    Route::get('tickets/{token}', [PublicQrController::class, 'verifyTicket'])->middleware('throttle:public');
    Route::get('tickets/{token}/qr', [PublicQrController::class, 'ticket'])->middleware('throttle:public');

    Route::middleware('tenant.resolve')->group(function (): void {
        Route::get('tenant', [PublicController::class, 'currentTenant']);
        Route::get('tenants/{tenantSlug}', [PublicController::class, 'tenant']);
        Route::get('tenants/{tenantSlug}/qr', [PublicQrController::class, 'tenant'])->middleware('throttle:public');
        Route::get('tenants/{tenantSlug}/events', [PublicController::class, 'events']);
        Route::get('tenants/{tenantSlug}/events/{eventSlug}/qr', [PublicQrController::class, 'event'])->middleware('throttle:public');
        Route::get('tenants/{tenantSlug}/events/{eventSlug}', [PublicController::class, 'event']);
        Route::post('tenants/{tenantSlug}/orders', [PublicController::class, 'createOrder'])->middleware('throttle:checkout');
        Route::get('tenants/{tenantSlug}/orders/{reference}', [PublicController::class, 'orderStatus']);
    });
});
