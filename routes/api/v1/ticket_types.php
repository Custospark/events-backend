<?php

use App\Http\Controllers\Api\TicketTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:events.manage'])->group(function (): void {
    Route::apiResource('ticket-types', TicketTypeController::class);
});
