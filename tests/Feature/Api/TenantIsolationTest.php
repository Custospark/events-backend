<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_tenant_admin_cannot_read_another_tenants_event(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'tenant-a']);
        $tenantB = $this->makeTenant(['slug' => 'tenant-b']);
        $adminA = $this->makeAdmin($tenantA);
        $eventB = $this->makeEvent($tenantB);

        Sanctum::actingAs($adminA);

        $this->getJson("/api/v1/events/{$eventB->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Event not found.');
    }

    public function test_tenant_admin_cannot_read_another_tenants_order(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'tenant-a']);
        $tenantB = $this->makeTenant(['slug' => 'tenant-b']);
        $adminA = $this->makeAdmin($tenantA);
        $eventB = $this->makeEvent($tenantB);

        $order = Order::query()->create([
            'tenant_id' => $tenantB->id,
            'event_id' => $eventB->id,
            'reference' => 'CSE-TENANT-B-001',
            'buyer_name' => 'Peter Okello',
            'buyer_email' => 'peter@example.com',
            'status' => 'pending',
            'currency' => 'UGX',
            'total' => 50000,
        ]);

        Sanctum::actingAs($adminA);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order not found.');
    }

    public function test_non_tenant_platform_routes_reject_regular_users(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/tenants')->assertForbidden();
    }
}
