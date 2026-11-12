<?php

use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('payments/callback', PaymentCallbackController::class)->middleware('throttle:webhooks');
Route::match(['get', 'post'], 'payments/ipn', PaymentWebhookController::class)->middleware('throttle:webhooks');
Route::match(['get', 'post'], 'payments/webhook', PaymentWebhookController::class)->middleware('throttle:webhooks');

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:payments.view'])->group(function (): void {
    Route::post('payments/{payment}/verify', [PaymentController::class, 'verify']);
    Route::post('payments/{payment}/mark-refunded', [PaymentController::class, 'markRefunded'])
        ->middleware('permission:payouts.manage');
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show']);
});
