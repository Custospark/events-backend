<?php

namespace App\Services;

use App\Mail\StandardEmail;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutAttachment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayoutWorkflowService
{
    private const ACTIVE_PAYOUT_STATUSES = ['pending', 'initiated', 'processing', 'paid'];

    public function lockedTenant(int $tenantId): Tenant
    {
        return Tenant::query()->whereKey($tenantId)->lockForUpdate()->first()
            ?? throw new RuntimeException('Tenant not found.');
    }

    /**
     * @return array{topup_total: float, paid: float, requested: float, available: float, organizer_earnings: float, paid_orders: int}
     */
    public function summary(Tenant $tenant): array
    {
        $orders = $this->ordersForTenant((int) $tenant->id);
        $payouts = $this->payoutsForTenant((int) $tenant->id);

        $earnings = round($orders->sum(fn ($order): float => (float) $order->total - (float) $order->processing_fee), 2);
        $paid = round((float) $payouts->where('status', 'paid')->sum('net_amount'), 2);
        $requested = round((float) $payouts->whereIn('status', ['pending', 'initiated', 'processing'])->sum('net_amount'), 2);

        return [
            'organizer_earnings' => $earnings,
            'paid_total' => $paid,
            'requested_total' => $requested,
            'available' => round(max(0, $earnings - $paid - $requested), 2),
            'paid_orders' => (int) $orders->count(),
            'collected_total' => round((float) $orders->sum('total'), 2),
            'processing_fees' => round((float) $orders->sum('processing_fee'), 2),
        ];
    }

    public function initiate(int $tenantId, array $data): Payout
    {
        return DB::transaction(function () use ($tenantId, $data): Payout {
            $tenant = $this->lockedTenant($tenantId);
            $summary = $this->summary($tenant);
            $amount100 = (int) round((float) $data['amount'] * 100);
            $available100 = (int) round((float) $summary['available'] * 100);

            if ($amount100 <= 0 || $amount100 > $available100) {
                throw ValidationException::withMessages([
                    'amount' => ['The requested payout amount cannot exceed the available payout balance.'],
                ]);
            }

            $amount = round(((float) $amount100) / 100, 2);

            $payout = Payout::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => null,
                'reference' => 'PR-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
                'gross_amount' => $amount,
                'commission_amount' => 0,
                'net_amount' => $amount,
                'currency' => $tenant->currency,
                'status' => 'initiated',
                'initiation' => 'organizer_request',
                'request_priority' => $data['priority'] ?? 'normal',
                'requested_note' => $data['note'] ?? null,
                'requested_at' => now(),
                'requested_by' => auth()->id(),
                'metadata' => [
                    'requested_by_name' => auth()->user()?->name,
                    'request_note' => $data['note'] ?? null,
                    'organizer_earnings' => $summary['organizer_earnings'],
                    'available_before' => $summary['available'],
                ],
            ]);

            $this->notifyPlatform($payout, $tenant, $amount);

            return $payout->load(['tenant', 'requestedBy']);
        });
    }

    /**
     * @return array{summary: array<string, float|int>, organisers: array<int, array<string, mixed>>, requests: Collection}
     */
    public function platformOverview(): array
    {
        $tenants = Tenant::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $organisers = [];

        foreach ($tenants as $tenant) {
            $summary = $this->summary($tenant);
            $organisers[] = ['id' => (int) $tenant->id,
                'tenant_id' => (int) $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'currency' => $tenant->currency,
                'settings' => $tenant->settings,
                ...$summary,
                'active_requests' => Payout::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['initiated', 'processing'])
                    ->count(),
            ];
        }

        $requests = Payout::query()
            ->with(['tenant', 'requestedBy', 'attachments'])
            ->whereIn('status', ['pending', 'initiated', 'processing'])
            ->orderByRaw("CASE request_priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->oldest('requested_at')
            ->get();

        $totals = [
            'organizer_earnings' => round(collect($organisers)->sum('organizer_earnings'), 2),
            'available' => round(collect($organisers)->sum('available'), 2),
            'requested_total' => round(collect($organisers)->sum('requested_total'), 2),
            'paid_total' => round(collect($organisers)->sum('paid_total'), 2),
            'active_requests' => (int) collect($organisers)->sum('active_requests'),
        ];

        $completedRequests = Payout::query()
            ->with(['tenant', 'requestedBy', 'attachments'])
            ->where('status', 'paid')
            ->latest('completed_at')
            ->limit(50)
            ->get();

        return [
            'summary' => $totals,
            'organisers' => $organisers,
            'requests' => $requests,
            'completed_requests' => $completedRequests,
        ];
    }

    public function markProcessing(Payout $payout, ?string $note = null): Payout
    {
        if (! in_array($payout->status, ['pending', 'initiated'], true)) {
            throw new RuntimeException('Only initiated requests can move into processing.');
        }

        $payout->forceFill([
            'status' => 'processing',
            'processing_started_at' => now(),
            'metadata' => array_merge($payout->metadata ?? [], ['processing_note' => $note]),
        ])->save();

        return $payout->fresh(['tenant', 'requestedBy', 'attachments']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function markPaid(Payout $payout, array $data, ?UploadedFile $evidence = null): Payout
    {
        if (! in_array($payout->status, ['initiated', 'processing'], true)) {
            throw new RuntimeException('Only initiated or processing payouts can be marked paid.');
        }

        $payout->forceFill([
            'status' => 'paid',
            'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
            'provider_reference' => $data['provider_reference'],
            'completed_at' => now(),
            'metadata' => array_merge($payout->metadata ?? [], [
                'payment_channel' => $data['channel'] ?? 'bank_counter',
                'payment_note' => $data['notes'] ?? null,
                'paid_by' => auth()->id(),
            ]),
        ])->save();

        if ($evidence instanceof UploadedFile) {
            $path = $evidence->store('payout-evidence/'.$payout->tenant_id.'/'.$payout->id);

            PayoutAttachment::query()->create([
                'tenant_id' => $payout->tenant_id,
                'payout_id' => $payout->id,
                'uploaded_by' => auth()->id(),
                'path' => $path,
                'original_name' => $evidence->getClientOriginalName(),
                'mime_type' => $evidence->getMimeType(),
                'size' => $evidence->getSize(),
            ]);
        }

        return $payout->fresh(['tenant', 'requestedBy', 'attachments']);
    }

    public function decline(Payout $payout, string $reason): Payout
    {
        if (! in_array($payout->status, ['pending', 'initiated'], true)) {
            throw new RuntimeException('Only initiated requests can be declined.');
        }

        $payout->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
            'decline_reason' => trim($reason),
        ])->save();

        return $payout->fresh(['tenant', 'requestedBy', 'attachments']);
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersForTenant(int $tenantId): Collection
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->get(['tenant_id', 'event_id', 'total', 'processing_fee']);
    }

    /**
     * @return Collection<int, Payout>
     */
    private function payoutsForTenant(int $tenantId): Collection
    {
        return Payout::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::ACTIVE_PAYOUT_STATUSES)
            ->get(['tenant_id', 'status', 'net_amount', 'requested_at', 'completed_at']);
    }

    private function notifyPlatform(Payout $payout, Tenant $tenant, float $amount): void
    {
        $recipients = collect(preg_split('/[,;]/', (string) config('events.platform_admin_emails', '')))
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            Log::warning('[Payout Workflow] Platform admin email list not configured.');

            return;
        }

        $availableBefore = (float) ($payout->metadata['available_before'] ?? $amount);
        $body = sprintf(
            '<p>A payout has been requested.</p><ul><li><strong>Organiser:</strong> %s</li><li><strong>Amount:</strong> %s %s</li><li><strong>Priority:</strong> %s</li><li><strong>Reference:</strong> %s</li><li><strong>Available before request:</strong> %s %s</li></ul><p>%s</p>',
            e($tenant->name),
            number_format($amount, 2),
            e($tenant->currency),
            e($payout->request_priority),
            e($payout->reference),
            number_format($availableBefore, 2),
            e($tenant->currency),
            $payout->requested_note ? nl2br(e((string) $payout->requested_note)) : 'No note provided.',
        );

        $frontend = rtrim((string) config('app.frontend_url', 'https://events.custospark.com'), '/');

        Mail::to($recipients)->send(new StandardEmail(
            title: 'New payout request from '.$tenant->name,
            mailBody: $body,
            ctaUrl: $frontend.'/platform/payouts',
            ctaLabel: 'Review payout',
            tip: 'Process initiated requests as soon as possible. Failed or declined requests are marked with a reason.',
        ));
    }
}
