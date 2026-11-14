<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payout;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use RuntimeException;

class PayoutService implements PayoutServiceInterface
{
    public function __construct(
        protected PayoutRepositoryInterface $payoutRepository,
        protected EventRepositoryInterface $eventRepository,
        protected TenantRepositoryInterface $tenantRepository,
    ) {}

    public function getAll(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->payoutRepository->all($tenantId, $filters, $perPage);
    }

    public function getById(int $tenantId, int $id): Payout
    {
        $payout = $this->payoutRepository->find($tenantId, $id);

        if ($payout === null) {
            throw new RuntimeException('Payout not found.');
        }

        return $payout;
    }

    public function settle(int $tenantId, int $eventId): Payout
    {
        $tenant = $this->tenantRepository->find($tenantId);
        $event = $this->eventRepository->find($tenantId, $eventId);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        if ($event === null) {
            throw new RuntimeException('Event not found.');
        }

        $existing = Payout::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->exists();

        if ($existing) {
            throw new RuntimeException('This event already has an active payout.');
        }

        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'paid')
            ->get(['total', 'processing_fee', 'gateway_fee', 'custospark_fee']);

        $gross = round((float) $orders->sum('total'), 2);

        if ($gross <= 0) {
            throw new RuntimeException('There are no paid orders to settle for this event.');
        }

        $gatewayFees = round((float) $orders->sum('gateway_fee'), 2);
        $custosparkFees = round((float) $orders->sum('custospark_fee'), 2);
        $processingFees = round((float) $orders->sum('processing_fee'), 2);
        $retainedFees = round($gatewayFees + $custosparkFees, 2);

        if ($retainedFees <= 0 && $processingFees > 0) {
            $retainedFees = $processingFees;
        }

        $retainedFees = min($retainedFees, $gross);

        return $this->payoutRepository->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'reference' => 'PO-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
            'gross_amount' => $gross,
            'commission_amount' => $retainedFees,
            'net_amount' => round($gross - $retainedFees, 2),
            'currency' => $tenant->currency,
            'status' => 'pending',
            'metadata' => [
                'event_title' => $event->title,
                'organiser_name' => $tenant->name,
                'processing_fees' => $retainedFees,
            ],
        ]);
    }
}
