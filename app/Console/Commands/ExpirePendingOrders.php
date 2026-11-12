<?php

namespace App\Console\Commands;

use App\Services\Contracts\OrderServiceInterface;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Expire unpaid orders whose payment window has passed and release their stock.';

    public function handle(OrderServiceInterface $orderService, TenantContext $tenantContext): int
    {
        $tenantContext->clear();

        $count = $orderService->expirePendingOrders();

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
