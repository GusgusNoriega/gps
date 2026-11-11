<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return ['device_id' => Device::factory(), 'event_id' => fake()->uuid(), 'latitude' => 4.65, 'longitude' => -74.08, 'recorded_at' => now(), 'received_at' => now(), 'accuracy' => 10];
    }
}
