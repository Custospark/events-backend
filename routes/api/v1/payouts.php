<?php

use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\PayoutWorkflowController;
use App\Http\Controllers\Api\TenantPayoutAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:payouts.view'])->group(function (): void {
    Route::get('tenant/payouts/summary', [PayoutWorkflowController::class, 'tenantSummary']);
    Route::get('tenant/payouts/requests', [PayoutWorkflowController::class, 'tenantRequests']);
    Route::post('tenant/payouts/requests', [PayoutWorkflowController::class, 'initiate']);
    Route::get('payout-account', [TenantPayoutAccountController::class, 'show']);
    Route::apiResource('payouts', PayoutController::class)->only(['index', 'show']);
    Route::get('payouts/{payout}/attachments', [PayoutController::class, 'attachments']);
    Route::get('payouts/{payout}/attachments/{attachment}/download', [PayoutController::class, 'downloadAttachment']);
});

Route::middleware(['throttle:api', 'auth:sanctum', 'platform', 'permission:platform.payouts'])->group(function (): void {
    Route::get('platform/payouts', [PayoutWorkflowController::class, 'overview']);
    Route::post('platform/payouts/{payout}/start-processing', [PayoutWorkflowController::class, 'startProcessing']);
    Route::post('platform/payouts/{payout}/mark-paid', [PayoutWorkflowController::class, 'markPaid']);
    Route::post('platform/payouts/{payout}/decline', [PayoutWorkflowController::class, 'decline']);
});

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:payouts.account.manage'])->group(function (): void {
    Route::patch('payout-account', [TenantPayoutAccountController::class, 'update']);
});
