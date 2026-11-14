<?php

use App\Http\Controllers\Api\PlatformOverviewController;
use App\Http\Controllers\Api\PlatformRoleController;
use App\Http\Controllers\Api\PlatformUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'platform'])->prefix('platform')->group(function (): void {
    Route::get('overview', [PlatformOverviewController::class, 'show'])
        ->middleware('permission:platform.overview');

    Route::middleware('permission:platform.users')->group(function (): void {
        Route::get('users', [PlatformUserController::class, 'index']);
        Route::post('users', [PlatformUserController::class, 'store']);
        Route::patch('users/{user}', [PlatformUserController::class, 'update']);
        Route::delete('users/{user}', [PlatformUserController::class, 'destroy']);

        Route::get('roles', [PlatformRoleController::class, 'index']);
        Route::post('roles', [PlatformRoleController::class, 'store']);
        Route::patch('roles/{role}', [PlatformRoleController::class, 'update']);
        Route::delete('roles/{role}', [PlatformRoleController::class, 'destroy']);
    });
});
