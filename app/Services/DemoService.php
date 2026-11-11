<?php

namespace App\Services;

use App\Events\FleetUpdated;
use App\Models\Device;
use App\Models\Setting;
use App\Models\TrackedAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoService
{
    public function __construct(private PositionIngestor $ingestor) {}

    public function seed(int $count = 4): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $names = ['Equipo Norte', 'Ruta Centro', 'Unidad Occidente', 'Equipo Chapinero'];
        for ($index = Device::where('is_simulated', true)->count(); $index < min($count, 200); $index++) {
            $device = DB::transaction(function () use ($index, $names) {
                $name = $names[$index % count($names)].($index >= 4 ? ' '.($index + 1) : '');
                $asset = TrackedAsset::create(['name' => $name, 'type' => $index % 2 ? 'vehicle' : 'phone', 'reference' => $index % 2 ? 'DEMO-'.($index + 1) : null]);
                $device = Device::create(['installation_id' => Str::uuid()->toString(), 'name' => $name, 'platform' => 'test', 'model' => 'Dispositivo simulado', 'group_name' => 'Demostración', 'is_simulated' => true, 'tracking_state' => 'active']);
                $device->assignments()->create(['tracked_asset_id' => $asset->id, 'starts_at' => now()->subDays(2)]);

                return $device;
            });
            $points = [];
            for ($step = 23; $step >= 0; $step--) {
                $points[] = $this->point($device, now()->subSeconds($step * 30)->toIso8601String(), $step);
            }
            $this->ingestor->ingest($device, $points);
        }
        Setting::current();
    }

    public function advance(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        foreach (Device::where('is_simulated', true)->where('status', 'active')->get() as $device) {
            $this->ingestor->ingest($device, [$this->point($device, now()->toIso8601String(), 0)]);
        }
    }

    public function clear(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        DB::transaction(function () {
            foreach (Device::where('is_simulated', true)->get() as $device) {
                $assetIds = $device->assignments()->pluck('tracked_asset_id');
                $device->tokens()->delete();
                $device->delete();
                TrackedAsset::whereIn('id', $assetIds)->whereDoesntHave('assignments')->delete();
            }
            FleetUpdated::dispatch();
        });
    }

    private function point(Device $device, string $date, int $step): array
    {
        $centers = [[4.6915, -74.055], [4.610, -74.076], [4.652, -74.112], [4.6505, -74.061]];
        [$latitude, $longitude] = $centers[($device->id - 1) % 4];
        $phase = (now()->timestamp - $step * 30) / 180 + $device->id;

        return ['event_id' => Str::uuid()->toString(), 'recorded_at' => $date, 'latitude' => $latitude + sin($phase) * 0.003, 'longitude' => $longitude + cos($phase) * 0.004, 'accuracy' => 8, 'speed' => $device->id % 2 ? 1.4 : 8.3, 'heading' => fmod(abs($phase * 40), 360), 'battery' => 90 - $device->id % 55];
    }
}
