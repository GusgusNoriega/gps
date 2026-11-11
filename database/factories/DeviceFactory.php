<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return ['installation_id' => fake()->uuid(), 'name' => fake()->firstName().' · teléfono', 'platform' => 'android', 'status' => 'active'];
    }
}
