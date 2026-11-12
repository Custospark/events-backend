<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'tenant_id' => Tenant::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'venue' => fake()->company(),
            'city' => fake()->city(),
            'country' => 'Uganda',
            'starts_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14)->addHours(8),
            'status' => 'published',
            'capacity' => 500,
            'currency' => 'UGX',
            'service_fee_percent' => 2,
            'published_at' => now(),
        ];
    }
}
