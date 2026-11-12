<?php

use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant.resolve', 'tenant.access', 'permission:events.manage'])->group(function (): void {
    Route::post('events/{event}/publish', [EventController::class, 'publish']);
    Route::post('events/{event}/unpublish', [EventController::class, 'unpublish']);
    Route::get('events/{event}/ticket-types', [EventController::class, 'ticketTypes']);
    Route::apiResource('events', EventController::class);
});
