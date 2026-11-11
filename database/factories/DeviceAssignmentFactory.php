<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\TrackedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['device_id' => Device::factory(), 'tracked_asset_id' => TrackedAsset::factory(), 'starts_at' => now()->subDay()];
    }
}
