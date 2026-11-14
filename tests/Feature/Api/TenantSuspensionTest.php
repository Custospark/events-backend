<?php

namespace Tests\Feature\Api;

use App\Mail\StandardEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class TenantSuspensionTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_platform_admin_can_suspend_tenant_and_staff_cannot_sign_in(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant, ['email' => 'admin@cocis.ac.ug']);
        $platformAdmin = $this->makeAdmin($tenant, ['is_platform_admin' => true]);

        Sanctum::actingAs($platformAdmin);

        $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", [
            'reason' => 'Chargeback of UGX 2,000,000 on order CSE-123.',
        ])->assertOk();

        $tenant = $tenant->fresh();
        $this->assertSame('suspended', $tenant->status);
        $this->assertSame('Chargeback of UGX 2,000,000 on order CSE-123.', $tenant->settings['suspension_reason']);
        $this->assertNotEmpty($tenant->settings['suspended_at']);
        $this->assertSame($platformAdmin->id, $tenant->settings['suspended_by']['id']);
        $this->assertSame($platformAdmin->email, $tenant->settings['suspended_by']['email']);
        $this->assertCount(1, $tenant->settings['suspension_history']);
        $this->assertSame($platformAdmin->id, $tenant->settings['suspension_history'][0]['suspended_by']['id']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This tenant account is not active.');

        $freshAdmin = User::query()->find($admin->id);
        $this->assertCount(0, $freshAdmin->tokens);

        Mail::assertSent(StandardEmail::class, function (StandardEmail $mail) use ($admin, $tenant): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->mailBody, 'Chargeback of UGX 2,000,000 on order CSE-123.')
                && str_contains($mail->mailBody, $tenant->name);
        });
    }

    public function test_suspending_twice_keeps_full_history(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant(['slug' => 'cocis-2']);
        $platformAdmin = $this->makeAdmin($tenant, ['is_platform_admin' => true]);

        Sanctum::actingAs($platformAdmin);

        $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", ['reason' => 'First reason'])->assertOk();
        Sanctum::actingAs($platformAdmin);
        $this->patchJson("/api/v1/tenants/{$tenant->id}", ['status' => 'active', 'settings' => $tenant->fresh()->settings])->assertOk();
        Sanctum::actingAs($platformAdmin);
        $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", ['reason' => 'Second reason'])->assertOk();

        $tenant = $tenant->fresh();
        $this->assertCount(2, $tenant->settings['suspension_history']);
        $this->assertSame('First reason', $tenant->settings['suspension_history'][0]['reason']);
        $this->assertNotEmpty($tenant->settings['suspension_history'][0]['activated_at']);
        $this->assertSame($platformAdmin->id, $tenant->settings['suspension_history'][0]['activated_by']['id']);
        $this->assertSame('Second reason', $tenant->settings['suspension_history'][1]['reason']);
        $this->assertArrayNotHasKey('activated_at', $tenant->settings['suspension_history'][1]);
    }

    public function test_suspend_endpoint_requires_reason(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis-3']);
        $platformAdmin = $this->makeAdmin($tenant, ['is_platform_admin' => true]);

        Sanctum::actingAs($platformAdmin);

        $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_regular_users_cannot_suspend_tenants(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis-4']);
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tenants/{$tenant->id}/suspend", ['reason' => 'Nope'])
            ->assertStatus(403);
    }
}
