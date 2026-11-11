<?php

namespace App\Services;

use App\Events\FleetUpdated;
use App\Models\Device;
use App\Models\Position;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PositionIngestor
{
    public function ingest(Device $device, array $points): array
    {
        return DB::transaction(function () use ($device, $points) {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            abort_unless($device->status === 'active', 403, 'Dispositivo desactivado.');
            $latest = $device->latestPosition;
            $results = [];
            $accepted = 0;
            foreach ($points as $index => $point) {
                $validator = Validator::make(is_array($point) ? $point : [], [
                    'event_id' => ['required', 'uuid'],
                    'recorded_at' => ['required', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/'],
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                    'accuracy' => ['nullable', 'numeric', 'between:0,100000'],
                    'speed' => ['nullable', 'numeric', 'between:0,400'],
                    'heading' => ['nullable', 'numeric', 'gte:0', 'lt:360'],
                    'battery' => ['nullable', 'integer', 'between:0,100'],
                ]);
                $eventId = is_array($point) && is_string($point['event_id'] ?? null) ? mb_substr($point['event_id'], 0, 100) : null;
                if ($validator->fails()) {
                    $results[] = ['index' => $index, 'event_id' => $eventId, 'status' => 'rejected', 'errors' => $validator->errors()->toArray()];

                    continue;
                }
                $data = $validator->validated();
                $recordedAt = CarbonImmutable::parse($data['recorded_at'])->utc();
                if ($recordedAt->isAfter(now()->addMinutes(2)) || $recordedAt->isBefore(now()->subYear())) {
                    $results[] = ['index' => $index, 'event_id' => $eventId, 'status' => 'rejected', 'errors' => ['recorded_at' => ['Fecha fuera del intervalo permitido.']]];

                    continue;
                }
                $existing = Position::where('device_id', $device->id)->where('event_id', $data['event_id'])->first();
                if ($existing) {
                    $same = abs($existing->latitude - (float) $data['latitude']) < 0.0000001 && abs($existing->longitude - (float) $data['longitude']) < 0.0000001 && $existing->recorded_at->timestamp === $recordedAt->timestamp;
                    $results[] = ['index' => $index, 'event_id' => $eventId, 'status' => $same ? 'duplicate' : 'rejected', 'reason' => $same ? 'already_received' : 'event_conflict'];

                    continue;
                }
                $assignment = $device->assignments()->where('starts_at', '<=', $recordedAt)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $recordedAt))->latest('starts_at')->first();
                $usable = ! isset($data['accuracy']) || $data['accuracy'] <= config('tracking.max_accuracy');
                $position = $device->positions()->create(array_merge($data, ['recorded_at' => $recordedAt, 'received_at' => now(), 'device_assignment_id' => $assignment?->id, 'is_usable' => $usable]));
                if ($usable && (! $latest || $recordedAt->isAfter($latest->recorded_at))) {
                    $latest = $position;
                    $device->latest_position_id = $position->id;
                }
                $accepted++;
                $results[] = ['index' => $index, 'event_id' => $eventId, 'status' => 'accepted', 'quality' => $usable ? 'usable' : 'low_accuracy'];
            }
            $device->last_seen_at = now();
            $device->save();
            FleetUpdated::dispatch();

            return ['accepted' => $accepted, 'results' => $results, 'server_time' => now()->toIso8601String()];
        }, 3);
    }
}
