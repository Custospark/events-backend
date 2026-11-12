<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/tenants.php';
    require __DIR__.'/api/v1/roles.php';
    require __DIR__.'/api/v1/users.php';
    require __DIR__.'/api/v1/events.php';
    require __DIR__.'/api/v1/ticket_types.php';
    require __DIR__.'/api/v1/orders.php';
    require __DIR__.'/api/v1/order_items.php';
    require __DIR__.'/api/v1/tickets.php';
    require __DIR__.'/api/v1/payments.php';
    require __DIR__.'/api/v1/payouts.php';
    require __DIR__.'/api/v1/dashboard.php';
    require __DIR__.'/api/v1/public.php';
});
