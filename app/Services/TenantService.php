<?php

namespace App\Services;

use App\Mail\StandardEmail;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\Permissions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class TenantService implements TenantServiceInterface
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository,
        protected RoleRepositoryInterface $roleRepository,
        protected UserRepositoryInterface $userRepository,
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->tenantRepository->all($filters, $perPage);
    }

    public function getById(int $id): Tenant
    {
        $tenant = $this->tenantRepository->find($id);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        return $tenant;
    }

    public function getBySlug(string $slug): Tenant
    {
        $tenant = $this->tenantRepository->findBySlug($slug);

        if ($tenant === null) {
            throw new RuntimeException('Tenant not found.');
        }

        return $tenant;
    }

    public function onboard(array $data): Tenant
    {
        if ($this->userRepository->findByEmail($data['admin_email']) !== null) {
            throw new RuntimeException('An account with the administrator email already exists.');
        }

        return DB::transaction(function () use ($data): Tenant {
            $tenant = $this->tenantRepository->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'domain' => $data['domain'] ?? null,
                'commission_percent' => $data['commission_percent'] ?? config('custospark.commission_percent', 5),
                'currency' => $data['currency'] ?? 'UGX',
                'timezone' => $data['timezone'] ?? 'Africa/Kampala',
                'status' => 'active',
                'settings' => $data['settings'] ?? null,
                'onboarded_at' => now(),
            ]);

            $adminRole = null;

            foreach (Permissions::defaultRoles() as $slug => $role) {
                $created = $this->roleRepository->create([
                    'tenant_id' => $tenant->id,
                    'name' => $role['name'],
                    'slug' => $slug,
                    'description' => "System {$role['name']} role.",
                    'permissions' => $role['permissions'],
                    'is_system' => true,
                ]);

                if ($slug === 'admin') {
                    $adminRole = $created;
                }
            }

            $this->userRepository->create([
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole?->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'password' => $data['admin_password'],
                'status' => 'active',
                'is_platform_admin' => false,
            ]);

            return $tenant->fresh();
        });
    }

    public function update(int $id, array $data, ?User $actor = null): Tenant
    {
        $tenant = $this->getById($id);
        unset($data['onboarded_at']);

        $activating = $tenant->status === 'suspended' && ($data['status'] ?? null) === 'active';

        if ($activating) {
            $settings = array_merge($tenant->settings ?? [], $data['settings'] ?? []);
            $history = $settings['suspension_history'] ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            if (count($history) > 0) {
                $last = count($history) - 1;
                $history[$last]['activated_at'] = now()->toIso8601String();
                $history[$last]['activated_by'] = $this->actorInfo($actor);
            }

            unset($settings['suspension_reason'], $settings['suspended_at'], $settings['suspended_by']);
            $settings['suspension_history'] = $history;
            $settings['reopened_at'] = now()->toIso8601String();
            $data['settings'] = $settings;
        }

        return $this->tenantRepository->update($tenant, $data);
    }

    public function delete(int $id): bool
    {
        return $this->tenantRepository->delete($this->getById($id));
    }

    public function suspend(int $id, string $reason, ?User $actor = null): Tenant
    {
        $tenant = $this->getById($id);
        $suspendedAt = now()->toIso8601String();
        $actorInfo = $this->actorInfo($actor);
        $settings = $tenant->settings ?? [];
        $history = $settings['suspension_history'] ?? [];

        if (! is_array($history)) {
            $history = [];
        }

        $history[] = [
            'reason' => $reason,
            'suspended_at' => $suspendedAt,
            'suspended_by' => $actorInfo,
        ];

        $settings['suspension_reason'] = $reason;
        $settings['suspended_at'] = $suspendedAt;
        $settings['suspended_by'] = $actorInfo;
        $settings['suspension_history'] = $history;

        $updated = $this->tenantRepository->update($tenant, [
            'status' => 'suspended',
            'settings' => $settings,
        ]);

        $tenant->users()->each(fn (User $user) => $user->tokens()->delete());

        $this->notifySuspension($updated, $reason, $actorInfo, $suspendedAt);

        return $updated;
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function actorInfo(?User $actor): ?array
    {
        if ($actor === null) {
            return null;
        }

        return [
            'id' => $actor->id,
            'name' => $actor->name,
            'email' => $actor->email,
        ];
    }

    private function notifySuspension(Tenant $tenant, string $reason, ?array $actor, string $suspendedAt): void
    {
        $admins = $tenant->users()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('[Tenant] No active tenant admins found to notify about suspension.', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $actorLine = $actor !== null
            ? sprintf(' by %s (%s)', e($actor['name']), e($actor['email']))
            : '';

        $body = sprintf(
            '<p>Hello,</p><p>Your Custospark Events workspace <strong>%s</strong> has been suspended by the Custospark Events Platform Team%s.</p><p><strong>Reason:</strong> %s</p><p><strong>Date:</strong> %s</p><p>While suspended, staff of this workspace cannot sign in or access ticket sales, attendees, orders or payouts.</p><p>If you believe this suspension is a mistake, please contact the Custospark Events Platform Team at <a href="mailto:events@custospark.com">events@custospark.com</a> and quote this email and your workspace name.</p>',
            e($tenant->name),
            $actorLine,
            nl2br(e($reason)),
            e($suspendedAt),
        );

        try {
            Mail::to($admins->pluck('email')->all())->send(new StandardEmail(
                title: 'Your Custospark Events workspace has been suspended',
                mailBody: $body,
                ctaUrl: null,
                ctaLabel: null,
                tip: 'If you do not recognise this suspension, contact the Custospark Events Platform Team immediately at events@custospark.com.',
            ));
        } catch (\Throwable $exception) {
            Log::warning('[Tenant] Suspension email could not be sent.', [
                'tenant_id' => $tenant->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
