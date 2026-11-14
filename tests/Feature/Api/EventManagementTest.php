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
            'processing_fee_bearer' => 'customer',
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

    public function test_tenant_admin_can_change_currency_and_prices_follow_it(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis', 'currency' => 'UGX']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant, [
            'currency' => 'UGX',
            'starts_at' => now()->addDays(30),
        ]);
        $ticketType = $this->makeTicketType($event, ['currency' => 'UGX', 'price' => 50000]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/tenant/currency', ['currency' => 'USD'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD');

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD');

        $this->getJson("/api/v1/events/{$event->id}/ticket-types")
            ->assertOk()
            ->assertJsonPath('data.0.currency', 'USD');

        $this->assertSame('UGX', $ticketType->fresh()->currency);
    }

    public function test_tenant_admin_can_choose_who_pays_the_processing_fee(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/events', [
            'title' => 'Processing Fee Event',
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'processing_fee_bearer' => 'organizer',
        ])->assertCreated()
            ->assertJsonPath('data.processing_fee_bearer', 'organizer')
            ->assertJsonPath('data.processing_fee_percent', '5.00');

        $eventId = $response->json('data.id');

        $this->patchJson("/api/v1/events/{$eventId}", [
            'processing_fee_bearer' => 'customer',
        ])->assertOk()
            ->assertJsonPath('data.processing_fee_bearer', 'customer');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'processing_fee_bearer' => 'customer',
        ]);
    }

    public function test_platform_admin_can_choose_who_pays_the_processing_fee(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $event = $this->makeEvent($tenant);
        $platformAdmin = $this->makeAdmin($tenant, [
            'tenant_id' => null,
            'role_id' => null,
            'is_platform_admin' => true,
        ]);

        Sanctum::actingAs($platformAdmin);

        $this->withHeader('X-Tenant', $tenant->slug)
            ->patchJson("/api/v1/events/{$event->id}", [
                'processing_fee_bearer' => 'organizer',
            ])
            ->assertOk()
            ->assertJsonPath('data.processing_fee_bearer', 'organizer');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'processing_fee_bearer' => 'organizer',
        ]);
    }

    public function test_admin_can_configure_group_ticket_admissions(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $admin = $this->makeAdmin($tenant);
        $event = $this->makeEvent($tenant);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/ticket-types', [
            'event_id' => $event->id,
            'name' => 'Couple ticket',
            'price' => 80000,
            'currency' => 'UGX',
            'quantity_total' => 50,
            'admits' => 2,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'status' => 'active',
            'sort_order' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.admits', 2);

        $ticketTypeId = $response->json('data.id');

        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketTypeId,
            'event_id' => $event->id,
            'admits' => 2,
        ]);

        $this->patchJson("/api/v1/ticket-types/{$ticketTypeId}", [
            'admits' => 3,
        ])->assertOk()
            ->assertJsonPath('data.admits', 3);
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
