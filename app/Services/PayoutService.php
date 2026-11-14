<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutAttachment;
use App\Models\Tenant;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Contracts\PayoutServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayoutService implements PayoutServiceInterface
{
    private const ACTIVE_PAYOUT_STATUSES = ['pending', 'processing', 'paid'];

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutstanding(int $tenantId): array
    {
        $tenant = $this->tenantRepository->find($tenantId);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        $orders = Order::query()
            ->with('event')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->get(['event_id', 'total', 'processing_fee']);

        $payouts = Payout::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::ACTIVE_PAYOUT_STATUSES)
            ->get(['event_id', 'net_amount']);

        $payoutsByEvent = $payouts->groupBy('event_id');

        return $orders
            ->groupBy('event_id')
            ->map(function (Collection $eventOrders, int|string $eventId) use ($payoutsByEvent, $tenant): array {
                $summary = $this->summaryForOrders($eventOrders);
                $paidOut = round(
                    (float) ($payoutsByEvent->get($eventId) ?? collect())->sum('net_amount'),
                    2,
                );
                $outstanding = round(max(0, $summary['organizer_earnings'] - $paidOut), 2);

                return [
                    'event_id' => (int) $eventId,
                    'event_title' => $eventOrders->first()?->event?->title ?? 'Event #'.$eventId,
                    'paid_orders' => $eventOrders->count(),
                    'collected_total' => $summary['collected_total'],
                    'processing_fees' => $summary['processing_fees'],
                    'organizer_earnings' => $summary['organizer_earnings'],
                    'already_paid_out' => $paidOut,
                    'payable_total' => $outstanding,
                    'currency' => $tenant->currency,
                ];
            })
            ->filter(fn (array $summary): bool => $summary['payable_total'] > 0)
            ->sortBy('event_title')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(int $tenantId, array $data): Payout
    {
        $eventId = $data['event_id'] ?? null;

        if (! is_numeric($eventId)) {
            throw new RuntimeException('Select the event this payout belongs to.');
        }

        return DB::transaction(function () use ($tenantId, $data, $eventId): Payout {
            $tenant = Tenant::query()
                ->whereKey($tenantId)
                ->lockForUpdate()
                ->first();

            if ($tenant === null) {
                throw new RuntimeException('Tenant not found.');
            }

            $event = $this->eventRepository->find($tenantId, (int) $eventId);

            if ($event === null) {
                throw new RuntimeException('Event not found for this tenant.');
            }

            $orders = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $event->id)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->get(['total', 'processing_fee']);

            $summary = $this->summaryForOrders($orders);

            if ($summary['paid_orders'] === 0) {
                throw ValidationException::withMessages([
                    'event_id' => ['This event has no paid orders yet.'],
                ]);
            }

            $alreadyPaidOut = round(
                (float) Payout::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->whereIn('status', self::ACTIVE_PAYOUT_STATUSES)
                    ->lockForUpdate()
                    ->sum('net_amount'),
                2,
            );

            $outstanding = round(max(0, $summary['organizer_earnings'] - $alreadyPaidOut), 2);

            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'event_id' => ['There is no outstanding payout for this event.'],
                ]);
            }

            $amount = array_key_exists('amount', $data) && $data['amount'] !== null
                ? round((float) $data['amount'], 2)
                : $outstanding;

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['The payout amount must be greater than zero.'],
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => ['The payout amount cannot exceed the remaining outstanding balance.'],
                ]);
            }

            $payout = $this->payoutRepository->create([
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'reference' => 'PO-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
                'gross_amount' => $amount,
                'commission_amount' => 0,
                'net_amount' => $amount,
                'currency' => $tenant->currency,
                'status' => 'paid',
                'provider_reference' => $data['provider_reference'],
                'paid_at' => isset($data['paid_at'])
                    ? Carbon::parse($data['paid_at'])
                    : now(),
                'metadata' => [
                    'event_title' => $event->title,
                    'organiser_name' => $tenant->name,
                    'paid_orders' => $summary['paid_orders'],
                    'collected_total' => $summary['collected_total'],
                    'processing_fees' => $summary['processing_fees'],
                    'organizer_earnings' => $summary['organizer_earnings'],
                    'already_paid_out' => $alreadyPaidOut,
                    'outstanding_before' => $outstanding,
                    'outstanding_after' => round($outstanding - $amount, 2),
                    'disbursement_channel' => $data['channel'] ?? 'bank_counter',
                    'bank_reference' => $data['provider_reference'],
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]);

            $evidence = $data['evidence'] ?? null;

            if ($evidence instanceof UploadedFile) {
                $path = $evidence->store(
                    'payout-evidence/'.$tenantId.'/'.$payout->id,
                );

                PayoutAttachment::query()->create([
                    'tenant_id' => $tenantId,
                    'payout_id' => $payout->id,
                    'uploaded_by' => auth()->id(),
                    'path' => $path,
                    'original_name' => $evidence->getClientOriginalName(),
                    'mime_type' => $evidence->getMimeType(),
                    'size' => $evidence->getSize(),
                ]);
            }

            return $payout->load('attachments');
        });
    }

    /**
     * @param  Collection<int, Order|Model>  $orders
     * @return array{paid_orders: int, collected_total: float, processing_fees: float, organizer_earnings: float}
     */
    private function summaryForOrders(Collection $orders): array
    {
        $collected = round((float) $orders->sum('total'), 2);
        $fees = round((float) $orders->sum('processing_fee'), 2);

        return [
            'paid_orders' => $orders->count(),
            'collected_total' => $collected,
            'processing_fees' => $fees,
            'organizer_earnings' => round(max(0, $collected - $fees), 2),
        ];
    }
}
