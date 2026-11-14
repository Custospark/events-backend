<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PlatformOverviewService
{
    public function overview(): array
    {
        $tenants = Tenant::query()
            ->get(['id', 'name', 'slug', 'domain', 'status', 'currency', 'commission_percent', 'onboarded_at', 'created_at', 'settings']);

        $active = $tenants->where('status', 'active')->count();
        $suspended = $tenants->where('status', 'suspended')->count();
        $total = $tenants->count();
        $cutoff = Carbon::now()->subDays(30);
        $onboardedLast30Days = $tenants
            ->filter(fn (Tenant $tenant) => $this->carbon($tenant->onboarded_at)?->greaterThanOrEqualTo($cutoff)
                || $this->carbon($tenant->created_at)?->greaterThanOrEqualTo($cutoff))
            ->count();
        $commissions = $tenants
            ->pluck('commission_percent')
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);
        $averageCommission = $commissions->isEmpty() ? 0 : round($commissions->avg(), 2);
        $currencyCounts = $tenants
            ->pluck('currency')
            ->filter()
            ->countBy()
            ->map(fn (int $count, string $currency): array => ['currency' => $currency, 'count' => $count])
            ->values()
            ->all();

        return [
            'metrics' => [
                'total' => $total,
                'active' => $active,
                'suspended' => $suspended,
                'onboarded_last_30_days' => $onboardedLast30Days,
                'average_commission' => $averageCommission,
            ],
            'currencies' => $currencyCounts,
            'recent' => $this->tenantList($tenants->sortByDesc(fn (Tenant $tenant) => $tenant->created_at?->getTimestamp() ?? 0)->take(6)),
            'suspended' => $this->suspendedList($tenants->where('status', 'suspended')->take(5)),
        ];
    }

    private function tenantList(Collection $tenants): Collection
    {
        return $tenants->map(fn (Tenant $tenant): array => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'status' => $tenant->status,
            'currency' => $tenant->currency,
            'commission_percent' => $tenant->commission_percent,
            'onboarded_at' => $tenant->onboarded_at?->toIso8601String(),
            'created_at' => $tenant->created_at?->toIso8601String(),
        ])->values();
    }

    private function suspendedList(Collection $tenants): Collection
    {
        return $tenants->map(fn (Tenant $tenant): array => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'currency' => $tenant->currency,
            'status' => $tenant->status,
            'suspension_reason' => $tenant->settings['suspension_reason'] ?? null,
            'suspended_at' => $tenant->settings['suspended_at'] ?? null,
            'suspended_by' => $tenant->settings['suspended_by'] ?? null,
        ])->values();
    }

    private function carbon(mixed $value): ?CarbonInterface
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : Carbon::parse($value);
    }
}
