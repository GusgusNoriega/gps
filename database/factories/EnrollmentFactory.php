<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return ['installation_id' => fake()->uuid(), 'name' => fake()->firstName(), 'platform' => 'android', 'secret_hash' => hash('sha256', fake()->uuid()), 'expires_at' => now()->addDay()];
    }
}
