<?php

namespace App\Console\Commands;

use App\Services\Contracts\PaymentServiceInterface;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=50 : Maximum number of pending payments to check}';

    protected $description = 'Re-check pending payments against the gateway and settle any missed callbacks.';

    public function handle(PaymentServiceInterface $paymentService, TenantContext $tenantContext): int
    {
        $tenantContext->clear();

        $counts = $paymentService->reconcilePending((int) $this->option('limit'));

        $this->table(
            ['Checked', 'Succeeded', 'Failed', 'Pending', 'Needs review', 'Errors'],
            [[$counts['checked'], $counts['succeeded'], $counts['failed'], $counts['pending'], $counts['review'], $counts['errors']]],
        );

        return self::SUCCESS;
    }
}
