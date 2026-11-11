<?php

namespace App\Http\Controllers\Api;

use App\Events\FleetUpdated;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Setting;
use App\Services\PositionIngestor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TelemetryController extends Controller
{
    public function store(Request $request, PositionIngestor $ingestor): JsonResponse
    {
        $data = $request->validate(['positions' => ['required', 'array', 'min:1', 'max:'.config('tracking.max_batch')], 'positions.*' => ['required', 'array']]);

        return response()->json($ingestor->ingest($request->user(), $data['positions']));
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate(['tracking_state' => ['required', Rule::in(['active', 'stopped', 'permission_missing', 'gps_disabled'])], 'recorded_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/']]);
        $recordedAt = CarbonImmutable::parse($data['recorded_at'])->utc();
        abort_if($recordedAt->isAfter(now()->addMinutes(2)) || $recordedAt->isBefore(now()->subDay()), 422, 'Fecha de estado no válida.');
        DB::transaction(function () use ($request, $data, $recordedAt) {
            $device = Device::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($device->status === 'active', 403);
            $device->last_seen_at = now();
            if (! $device->state_recorded_at || $recordedAt->isAfter($device->state_recorded_at)) {
                $device->tracking_state = $data['tracking_state'];
                $device->state_recorded_at = $recordedAt;
            }
            $device->save();
            FleetUpdated::dispatch();
        });

        return response()->json(['status' => 'received', 'server_time' => now()->toIso8601String()]);
    }

    public function configuration(Request $request): JsonResponse
    {
        $settings = Setting::current();

        return response()->json(['name' => $request->user()->name, 'moving_interval_seconds' => $settings->moving_interval, 'stationary_interval_seconds' => $settings->stationary_interval, 'max_batch_size' => config('tracking.max_batch'), 'server_time' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }
}
