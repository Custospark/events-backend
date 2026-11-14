<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class CocisFinalistsDinnerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'cocis')->first();

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);

        $event = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'cocis-class-of-2027-finalists-dinner')
            ->first();

        if ($event === null) {
            $event = Event::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', 'cocis-annual-tech-conference-2026')
                ->first();
        }

        $startsAt = CarbonImmutable::parse('2027-03-20 18:00:00', 'Africa/Kampala');
        $endsAt = CarbonImmutable::parse('2027-03-20 23:00:00', 'Africa/Kampala');

        $attributes = [
            'title' => "CoCIS Class of 2027 Finalists' Dinner",
            'slug' => 'cocis-class-of-2027-finalists-dinner',
            'description' => 'An evening of celebration, mentorship and networking as the College of Computing and Information Sciences Class of 2027 transitions from Makerere University into industry, research and public service.',
            'venue' => 'Arirang Hotel',
            'address' => null,
            'city' => 'Kampala',
            'country' => 'Uganda',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Africa/Kampala',
            'status' => 'published',
            'capacity' => 750,
            'currency' => 'UGX',
            'processing_fee_bearer' => 'customer',
        ];

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

        $ticketTypes = [
            [
                'name' => 'Single',
                'description' => 'Admits one guest',
                'price' => 120000,
                'quantity_total' => 500,
                'admits' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => 'Couple',
                'description' => 'Admits two guests',
                'price' => 200000,
                'quantity_total' => 250,
                'admits' => 2,
                'sort_order' => 2,
            ],
        ];

        foreach ($ticketTypes as $ticketType) {
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
            ->whereNotIn('name', ['Single', 'Couple'])
            ->update(['status' => 'inactive']);

        app(TenantContext::class)->clear();
    }
}
