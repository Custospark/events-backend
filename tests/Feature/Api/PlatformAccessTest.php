<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    private function platformRole(string $slug): Role
    {
        return Role::query()->whereNull('tenant_id')->where('slug', $slug)->firstOrFail();
    }

    private function makeScopedPlatformAdmin(Role $role, array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'tenant_id' => null,
            'role_id' => $role->id,
            'name' => 'Scoped Admin',
            'email' => 'scoped-'.uniqid().'@events.custospark.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_platform_admin' => true,
        ], $overrides));
    }

    public function test_platform_roles_are_seeded_and_scoped_admin_is_limited(): void
    {
        $scoped = $this->makeScopedPlatformAdmin($this->platformRole('viewer'));

        Sanctum::actingAs($scoped);

        $this->getJson('/api/v1/platform/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => ['metrics' => ['total', 'active', 'suspended', 'onboarded_last_30_days', 'average_commission'], 'recent', 'suspended', 'currencies']]);

        $this->getJson('/api/v1/tenants')->assertStatus(403);
        $this->getJson('/api/v1/platform/users')->assertStatus(403);
        $this->getJson('/api/v1/platform/payouts')->assertStatus(403);

        $finance = $this->makeScopedPlatformAdmin($this->platformRole('finance'));
        Sanctum::actingAs($finance);
        $this->getJson('/api/v1/platform/payouts')->assertOk();
        $this->getJson('/api/v1/tenants')->assertStatus(403);
    }

    public function test_platform_admin_without_role_is_full_superuser(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('viewer'));
        $super->update(['role_id' => null]);

        Sanctum::actingAs($super);

        $this->getJson('/api/v1/tenants')->assertOk();
        $this->getJson('/api/v1/platform/users')->assertOk();
        $this->getJson('/api/v1/platform/payouts')->assertOk();
    }

    public function test_super_admin_can_create_and_assign_platform_team_with_scope(): void
    {
        $tenantRole = Role::query()->create([
            'tenant_id' => $this->makeTenant()->id,
            'name' => 'Tenant Admin',
            'slug' => 'admin',
            'permissions' => ['users.manage'],
            'is_system' => true,
        ]);

        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        Sanctum::actingAs($super);

        $this->postJson('/api/v1/platform/users', [
            'name' => 'Finance Ops',
            'email' => 'finance-ops@events.custospark.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'role_id' => $this->platformRole('finance')->id,
        ])->assertCreated()
            ->assertJsonPath('data.role.slug', 'finance')
            ->assertJsonPath('data.is_platform_admin', true);

        $this->postJson('/api/v1/platform/users', [
            'name' => 'Bad Role',
            'email' => 'bad-role@events.custospark.com',
            'password' => 'secret-pass-2',
            'password_confirmation' => 'secret-pass-2',
            'role_id' => $tenantRole->id,
        ])->assertStatus(422);

        $financeOps = User::query()->where('email', 'finance-ops@events.custospark.com')->firstOrFail();
        Sanctum::actingAs($financeOps);
        $this->getJson('/api/v1/platform/payouts')->assertOk();
        $this->getJson('/api/v1/tenants')->assertStatus(403);

        Sanctum::actingAs($super);
        $this->patchJson("/api/v1/platform/users/{$financeOps->id}", [
            'role_id' => $this->platformRole('manager')->id,
        ])->assertOk()
            ->assertJsonPath('data.role.slug', 'manager');

        Sanctum::actingAs($financeOps->fresh());
        $this->getJson('/api/v1/tenants')->assertOk();
        $this->getJson('/api/v1/platform/users')->assertStatus(403);
    }

    public function test_platform_team_cannot_remove_the_last_active_administrator(): void
    {
        $only = $this->makeScopedPlatformAdmin($this->platformRole('owner'));

        Sanctum::actingAs($only);

        $this->deleteJson("/api/v1/platform/users/{$only->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own platform administrator account.');

        $this->patchJson("/api/v1/platform/users/{$only->id}", ['status' => 'suspended'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot suspend your own platform administrator account.');
    }

    public function test_super_admin_can_delete_a_scoped_admin(): void
    {
        $first = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        $second = $this->makeScopedPlatformAdmin($this->platformRole('viewer'));

        Sanctum::actingAs($first);

        $this->deleteJson("/api/v1/platform/users/{$second->id}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $second->id]);
    }

    public function test_restricted_admin_cannot_suspend_demote_or_delete_the_last_full_access_admin(): void
    {
        $onlyFullAccess = $this->makeScopedPlatformAdmin($this->platformRole('owner'));

        $teamRole = Role::query()->create([
            'tenant_id' => null,
            'name' => 'Team Admin',
            'slug' => 'team-admin',
            'permissions' => ['platform.users', 'platform.overview'],
            'is_system' => false,
        ]);
        $restricted = $this->makeScopedPlatformAdmin($teamRole);

        Sanctum::actingAs($restricted);

        $this->patchJson("/api/v1/platform/users/{$onlyFullAccess->id}", ['status' => 'suspended'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'At least one active platform administrator with full access must remain. Assign a full-access role or remove a restricted one before continuing.');

        $this->patchJson("/api/v1/platform/users/{$onlyFullAccess->id}", ['role_id' => $this->platformRole('viewer')->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'At least one active platform administrator with full access must remain. Assign a full-access role or remove a restricted one before continuing.');

        $this->deleteJson("/api/v1/platform/users/{$onlyFullAccess->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'At least one active platform administrator with full access must remain. Assign a full-access role or remove a restricted one before continuing.');
    }

    public function test_demoting_a_full_access_admin_is_allowed_when_another_full_access_remains(): void
    {
        $first = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        $second = $this->makeScopedPlatformAdmin($this->platformRole('owner'));

        Sanctum::actingAs($first);

        $this->patchJson("/api/v1/platform/users/{$second->id}", ['role_id' => $this->platformRole('finance')->id])
            ->assertOk()
            ->assertJsonPath('data.role.slug', 'finance');
    }

    public function test_promoting_a_restricted_admin_to_full_access_is_allowed(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        $restricted = $this->makeScopedPlatformAdmin($this->platformRole('viewer'));

        Sanctum::actingAs($super);

        $this->patchJson("/api/v1/platform/users/{$restricted->id}", ['role_id' => null])
            ->assertOk()
            ->assertJsonPath('data.role_id', null);
    }

    public function test_deleting_team_member_as_super_admin(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        $target = $this->makeScopedPlatformAdmin($this->platformRole('viewer'));

        Sanctum::actingAs($super);

        $this->deleteJson("/api/v1/platform/users/{$target->id}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_custom_platform_role_lifecycle(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));

        Sanctum::actingAs($super);

        $this->postJson('/api/v1/platform/roles', [
            'name' => 'Payouts Auditor',
            'slug' => 'payouts-auditor',
            'permissions' => ['platform.payouts'],
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'payouts-auditor')
            ->assertJsonPath('data.is_system', false);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'payouts-auditor')->firstOrFail();

        $this->postJson('/api/v1/platform/users', [
            'name' => 'Auditor Bot',
            'email' => 'auditor-bot@events.custospark.com',
            'password' => 'secret-pass-3',
            'password_confirmation' => 'secret-pass-3',
            'role_id' => $role->id,
        ])->assertCreated();

        $this->deleteJson("/api/v1/platform/roles/{$role->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This platform role is assigned to team members. Reassign them before deleting the role.');

        $auditor = User::query()->where('email', 'auditor-bot@events.custospark.com')->firstOrFail();
        $this->deleteJson("/api/v1/platform/users/{$auditor->id}")->assertNoContent();
        $this->deleteJson("/api/v1/platform/roles/{$role->id}")->assertNoContent();
    }

    public function test_system_platform_roles_cannot_be_modified_or_deleted(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));
        $viewer = $this->platformRole('viewer');

        Sanctum::actingAs($super);

        $this->patchJson("/api/v1/platform/roles/{$viewer->id}", ['name' => 'Renamed'])
            ->assertStatus(422);
        $this->deleteJson("/api/v1/platform/roles/{$viewer->id}")->assertStatus(422);
    }

    public function test_platform_role_rejects_unknown_permissions(): void
    {
        $super = $this->makeScopedPlatformAdmin($this->platformRole('owner'));

        Sanctum::actingAs($super);

        $this->postJson('/api/v1/platform/roles', [
            'name' => 'Bogus',
            'slug' => 'bogus',
            'permissions' => ['platform.superpower'],
        ])->assertStatus(422);
    }

    public function test_regular_tenant_admin_cannot_reach_platform_team(): void
    {
        $tenant = $this->makeTenant(['slug' => 'regular-tenant']);
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/users')->assertStatus(403);
        $this->getJson('/api/v1/platform/overview')->assertStatus(403);
        $this->getJson('/api/v1/platform/roles')->assertStatus(403);
    }
}
