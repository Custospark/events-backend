<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class GuluEventsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'gulu')->first();

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);

        $events = [
            [
                'title' => 'Gulu City Marathon 2027',
                'slug' => 'gulu-city-marathon-2027',
                'description' => 'A citywide marathon bringing together runners, families and businesses from across Gulu and northern Uganda.',
                'venue' => 'Kaunda Grounds',
                'address' => null,
                'city' => 'Gulu',
                'country' => 'Uganda',
                'starts_at' => CarbonImmutable::parse('2027-05-15 06:30:00', 'Africa/Kampala'),
                'ends_at' => CarbonImmutable::parse('2027-05-15 12:00:00', 'Africa/Kampala'),
                'capacity' => 2000,
                'tickets' => [
                    [
                        'name' => 'General',
                        'description' => 'Standard race entry',
                        'price' => 20000,
                        'quantity_total' => 1500,
                        'admits' => 1,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'VIP',
                        'description' => 'VIP race entry',
                        'price' => 100000,
                        'quantity_total' => 200,
                        'admits' => 1,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'title' => 'Gulu Business Expo 2026',
                'slug' => 'gulu-business-expo-2026',
                'description' => 'A showcase of businesses, startups and services from Gulu and across northern Uganda.',
                'venue' => 'Gulu Main Market',
                'address' => null,
                'city' => 'Gulu',
                'country' => 'Uganda',
                'starts_at' => CarbonImmutable::parse('2026-08-14 09:00:00', 'Africa/Kampala'),
                'ends_at' => CarbonImmutable::parse('2026-08-15 18:00:00', 'Africa/Kampala'),
                'capacity' => 1500,
                'tickets' => [
                    [
                        'name' => 'General',
                        'description' => 'Standard expo entry',
                        'price' => 15000,
                        'quantity_total' => 1000,
                        'admits' => 1,
                        'sort_order' => 1,
                    ],
                    [
                        'name' => 'VIP',
                        'description' => 'VIP expo entry',
                        'price' => 75000,
                        'quantity_total' => 200,
                        'admits' => 1,
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        foreach ($events as $eventData) {
            $tickets = $eventData['tickets'];
            unset($eventData['tickets']);

            $attributes = [
                ...$eventData,
                'timezone' => 'Africa/Kampala',
                'status' => 'published',
                'currency' => 'UGX',
                'processing_fee_bearer' => 'customer',
            ];

            $event = Event::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $attributes['slug'])
                ->first();

            if ($event === null) {
                $event = Event::query()->create([
                    'tenant_id' => $tenant->id,
                    'created_by' => $tenant->users()->orderBy('id')->value('id'),
                    'published_at' => now(),
                    ...$attributes,
                ]);
            } else {
                $event->update([
                    'published_at' => $event->published_at ?? now(),
                    ...$attributes,
                ]);
            }

            foreach ($tickets as $ticketType) {
                TicketType::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'event_id' => $event->id,
                        'name' => $ticketType['name'],
                    ],
                    [
                        'currency' => 'UGX',
                        'status' => 'active',
                        'min_per_order' => 1,
                        'max_per_order' => 10,
                        ...$ticketType,
                    ],
                );
            }

            TicketType::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->whereNotIn('name', array_column($tickets, 'name'))
                ->update(['status' => 'inactive']);
        }

        app(TenantContext::class)->clear();
    }
}
