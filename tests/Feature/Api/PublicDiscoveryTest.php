<?php

namespace Tests\Feature\Api;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class PublicDiscoveryTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_landing_lists_published_events_from_active_tenants_only(): void
    {
        $active = $this->makeTenant(['slug' => 'cocis']);
        $suspended = $this->makeTenant(['slug' => 'inactive', 'status' => 'suspended']);

        $visible = $this->makeEvent($active, ['title' => 'Visible Expo', 'slug' => 'visible-expo']);
        $this->makeEvent($active, ['title' => 'Draft Expo', 'slug' => 'draft-expo', 'status' => 'draft']);
        $this->makeEvent($suspended, ['title' => 'Hidden Expo', 'slug' => 'hidden-expo']);

        $response = $this->getJson('/api/v1/public/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.title', 'Visible Expo')
            ->assertJsonPath('data.0.tenant.slug', 'cocis');

        $this->assertSame('cocis', $response->json('data.0.tenant.slug'));
    }

    public function test_landing_search_and_city_filters_apply(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $this->makeEvent($tenant, [
            'title' => 'Kampala Tech Summit',
            'slug' => 'kampala-tech-summit',
            'city' => 'Kampala',
        ]);
        $this->makeEvent($tenant, [
            'title' => 'Entebbe Business Forum',
            'slug' => 'entebbe-business-forum',
            'city' => 'Entebbe',
        ]);

        $this->getJson('/api/v1/public/events?search=tech')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kampala Tech Summit');

        $this->getJson('/api/v1/public/events?city=Entebbe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Entebbe Business Forum');
    }

    public function test_landing_prioritises_ongoing_then_upcoming_then_past_events(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);

        $ongoing = $this->makeEvent($tenant, [
            'title' => 'Ongoing Expo',
            'slug' => 'ongoing-expo',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ]);
        $upcomingSoon = $this->makeEvent($tenant, [
            'title' => 'Upcoming Soon Expo',
            'slug' => 'upcoming-soon-expo',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(4),
        ]);
        $upcomingLater = $this->makeEvent($tenant, [
            'title' => 'Upcoming Later Expo',
            'slug' => 'upcoming-later-expo',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
        ]);
        $past = $this->makeEvent($tenant, [
            'title' => 'Past Expo',
            'slug' => 'past-expo',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(5)->addHours(4),
        ]);

        $this->getJson('/api/v1/public/events')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $ongoing->id)
            ->assertJsonPath('data.1.id', $upcomingSoon->id)
            ->assertJsonPath('data.2.id', $upcomingLater->id)
            ->assertJsonPath('data.3.id', $past->id);

        $this->getJson('/api/v1/public/events?upcoming=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $upcomingSoon->id)
            ->assertJsonPath('data.1.id', $upcomingLater->id);
    }

    public function test_public_tenant_page_lists_all_its_published_events_by_status(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $other = $this->makeTenant(['slug' => 'other']);

        $ongoing = $this->makeEvent($tenant, [
            'title' => 'Public Tenant Expo',
            'slug' => 'public-tenant-expo',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
        ]);
        $this->makeTicketType($ongoing, ['name' => 'General', 'admits' => 2]);

        $past = $this->makeEvent($tenant, [
            'title' => 'Past Tenant Expo',
            'slug' => 'past-tenant-expo',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(9),
        ]);

        $this->makeEvent($tenant, [
            'title' => 'Hidden Draft',
            'slug' => 'hidden-draft',
            'status' => 'draft',
        ]);
        $this->makeEvent($other, [
            'title' => 'Other Tenant Expo',
            'slug' => 'other-tenant-expo',
        ]);

        $this->getJson('/api/v1/public/tenants/cocis')
            ->assertOk()
            ->assertJsonPath('data.slug', 'cocis')
            ->assertJsonPath('data.name', $tenant->name);

        $this->getJson('/api/v1/public/tenants/cocis/events')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ongoing->id)
            ->assertJsonPath('data.1.id', $past->id)
            ->assertJsonPath('data.0.tenant.slug', 'cocis')
            ->assertJsonPath('data.0.ticket_types.0.admits', 2);
    }

    public function test_discovery_ignores_any_stale_tenant_context(): void
    {
        $first = $this->makeTenant(['slug' => 'first']);
        $second = $this->makeTenant(['slug' => 'second']);

        $this->makeEvent($first, ['title' => 'First Expo', 'slug' => 'first-expo']);
        $this->makeEvent($second, ['title' => 'Second Expo', 'slug' => 'second-expo']);

        app(TenantContext::class)->set($first);

        $this->getJson('/api/v1/public/events')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
