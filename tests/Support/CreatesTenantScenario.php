<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;

trait CreatesTenantScenario
{
    protected function resetTenantContext(): void
    {
        app(TenantContext::class)->clear();
    }

    protected function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create($overrides);
    }

    protected function makeAdmin(Tenant $tenant, array $overrides = []): User
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => [],
            'is_system' => true,
        ]);

        return User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'COCIS Administrator',
            'email' => 'admin-'.uniqid().'@cocis.ac.ug',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_platform_admin' => false,
        ], $overrides));
    }

    protected function makeEvent(Tenant $tenant, array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'title' => 'COCIS Innovation Expo',
            'slug' => 'cocis-innovation-expo-'.uniqid(),
            'description' => 'Student projects and research demos.',
            'venue' => 'COCIS Auditorium',
            'city' => 'Kampala',
            'country' => 'Uganda',
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(8),
            'timezone' => 'Africa/Kampala',
            'status' => 'published',
            'capacity' => 300,
            'currency' => 'UGX',
            'processing_fee_bearer' => 'customer',
            'published_at' => now(),
        ], $overrides));
    }

    protected function makeTicketType(Event $event, array $overrides = []): TicketType
    {
        return TicketType::query()->create(array_merge([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Student',
            'price' => 50000,
            'currency' => 'UGX',
            'quantity_total' => 100,
            'quantity_sold' => 0,
            'admits' => 1,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => 'active',
        ], $overrides));
    }
}
