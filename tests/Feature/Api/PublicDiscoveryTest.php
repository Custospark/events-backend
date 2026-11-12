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

    public function test_landing_hides_events_that_already_started_by_default(): void
    {
        $tenant = $this->makeTenant(['slug' => 'cocis']);
        $this->makeEvent($tenant, [
            'title' => 'Future Expo',
            'slug' => 'future-expo',
            'starts_at' => now()->addDays(5),
        ]);
        $this->makeEvent($tenant, [
            'title' => 'Past Expo',
            'slug' => 'past-expo',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(5)->addHours(4),
        ]);

        $this->getJson('/api/v1/public/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Future Expo');

        $this->getJson('/api/v1/public/events?upcoming=0')
            ->assertOk()
            ->assertJsonCount(2, 'data');
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
