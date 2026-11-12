<?php

use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:tickets.checkin'])->group(function (): void {
    Route::post('tickets/verify', [TicketController::class, 'verify']);
    Route::post('tickets/check-in', [TicketController::class, 'checkIn']);
    Route::get('tickets/code/{code}', [TicketController::class, 'findByCode']);
    Route::get('tickets/{ticket}/qr', [TicketController::class, 'qr']);
    Route::apiResource('tickets', TicketController::class)->only(['index', 'show']);
});
