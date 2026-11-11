<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrackedAssetFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->word(), 'type' => 'phone'];
    }
}
