<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => Event::query()->find($attributes['event_id'])?->tenant_id,
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General', 'VIP', 'Student', 'Staff']),
            'price' => fake()->numberBetween(10000, 200000),
            'currency' => 'UGX',
            'quantity_total' => 100,
            'quantity_sold' => 0,
            'admits' => 1,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => 'active',
        ];
    }
}
