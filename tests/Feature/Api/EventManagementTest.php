<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_admin_can_create_and_publish_an_event(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'COCIS Tech Conference 2026',
            'description' => 'Annual college conference.',
            'venue' => 'COCIS Auditorium',
            'city' => 'Kampala',
            'country' => 'Uganda',
            'starts_at' => now()->addDays(30)->toIso8601String(),
            'ends_at' => now()->addDays(30)->addHours(8)->toIso8601String(),
            'capacity' => 500,
            'currency' => 'UGX',
            'service_fee_percent' => 2,
        ])->assertCreated();

        $eventId = $response->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'tenant_id' => $tenant->id,
            'title' => 'COCIS Tech Conference 2026',
            'created_by' => $admin->id,
        ]);

        $this->postJson("/api/v1/events/{$eventId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull(Event::query()->find($eventId)->published_at);
    }

    public function test_event_slugs_are_unique_per_tenant(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $payload = [
            'title' => 'Repeated Title',
            'slug' => 'repeated-title',
            'starts_at' => now()->addDays(10)->toIso8601String(),
        ];

        $this->postJson('/api/v1/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'repeated-title');

        $this->postJson('/api/v1/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'repeated-title-1');
    }

    public function test_events_are_listed_only_for_the_current_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'tenant-a']);
        $tenantB = $this->makeTenant(['slug' => 'tenant-b']);
        $adminA = $this->makeAdmin($tenantA);

        $this->makeEvent($tenantA, ['title' => 'Tenant A Event']);
        $this->makeEvent($tenantB, ['title' => 'Tenant B Event']);

        Sanctum::actingAs($adminA);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tenant A Event');
    }

    public function test_cannot_publish_an_event_that_already_started(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant, [
            'status' => 'draft',
            'starts_at' => now()->subDay(),
            'published_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/events/{$event->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('message', 'An event that has already started cannot be published.');
    }
}
