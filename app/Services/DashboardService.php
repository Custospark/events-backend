<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Ticket;
use App\Services\Contracts\DashboardServiceInterface;

class DashboardService implements DashboardServiceInterface
{
    public function summary(int $tenantId): array
    {
        $paidOrders = Order::query()->where('tenant_id', $tenantId)->where('status', 'paid');

        return [
            'events' => [
                'total' => Event::query()->where('tenant_id', $tenantId)->count(),
                'published' => Event::query()->where('tenant_id', $tenantId)->where('status', 'published')->count(),
                'upcoming' => Event::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'published')
                    ->where('starts_at', '>=', now())
                    ->count(),
            ],
            'orders' => [
                'total' => Order::query()->where('tenant_id', $tenantId)->count(),
                'paid' => (clone $paidOrders)->count(),
                'pending' => Order::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                'revenue' => round((float) (clone $paidOrders)->sum('total'), 2),
            ],
            'tickets' => [
                'valid' => Ticket::query()->where('tenant_id', $tenantId)->where('status', 'valid')->count(),
                'checked_in' => Ticket::query()->where('tenant_id', $tenantId)->where('status', 'used')->count(),
            ],
            'payouts' => [
                'pending' => Payout::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                'paid_total' => round((float) Payout::query()->where('tenant_id', $tenantId)->where('status', 'paid')->sum('net_amount'), 2),
            ],
            'recent_orders' => Order::query()
                ->where('tenant_id', $tenantId)
                ->with('event:id,title')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }
}
