<?php

namespace App\Services;

use App\Events\FleetUpdated;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Enrollment;
use App\Models\TrackedAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrollmentService
{
    public function request(array $attributes): array
    {
        $secret = Str::random(64);
        $attributes['name'] = 'Dispositivo '.strtoupper(substr(str_replace('-', '', $attributes['installation_id']), 0, 8));
        $enrollment = Enrollment::create($attributes + ['secret_hash' => hash('sha256', $secret), 'expires_at' => now()->addDay()]);

        return ['enrollment_id' => $enrollment->id, 'enrollment_secret' => $secret, 'status' => 'pending', 'expires_at' => $enrollment->expires_at->toIso8601String(), 'poll_after_seconds' => 10];
    }

    public function approve(Enrollment $enrollment, ?int $assetId = null, ?string $name = null): Device
    {
        return DB::transaction(function () use ($enrollment, $assetId, $name) {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($enrollment->status === 'pending' && $enrollment->expires_at->isFuture(), 409, 'Esta solicitud ya fue atendida o venció.');
            $deviceName = $name ?? $enrollment->name;
            $asset = $assetId ? TrackedAsset::query()->lockForUpdate()->findOrFail($assetId) : TrackedAsset::create(['name' => $deviceName, 'type' => 'phone']);
            $oldAssignments = DeviceAssignment::where('tracked_asset_id', $asset->id)->whereNull('ends_at')->get();
            foreach ($oldAssignments as $assignment) {
                $oldDevice = Device::query()->lockForUpdate()->findOrFail($assignment->device_id);
                $oldDevice->update(['status' => 'disabled']);
                $oldDevice->tokens()->delete();
                $assignment->update(['ends_at' => now()]);
                AuditLog::record('device.replaced', $oldDevice->name);
            }
            $device = Device::create(['installation_id' => $enrollment->installation_id, 'name' => $deviceName, 'platform' => $enrollment->platform, 'model' => $enrollment->model]);
            $device->assignments()->create(['tracked_asset_id' => $asset->id, 'starts_at' => now()]);
            $enrollment->update(['name' => $deviceName, 'status' => 'approved', 'device_id' => $device->id, 'expires_at' => now()->addDay()]);
            AuditLog::record('enrollment.approved', $device->name, ['installation_id' => $device->installation_id]);
            FleetUpdated::dispatch();

            return $device;
        });
    }

    public function exchange(Enrollment $enrollment): array
    {
        return DB::transaction(function () use ($enrollment) {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($enrollment->expires_at->isFuture(), 410, 'La solicitud venció.');
            abort_unless($enrollment->status === 'approved' && ! $enrollment->exchanged_at, 409, 'La solicitud no está aprobada o ya se intercambió.');
            $device = Device::query()->lockForUpdate()->findOrFail($enrollment->device_id);
            abort_unless($device->status === 'active', 403, 'Dispositivo desactivado.');
            $token = $device->createToken('installation', ['telemetry:write'])->plainTextToken;
            $enrollment->update(['exchanged_at' => now(), 'status' => 'exchanged']);

            return ['device_id' => $device->id, 'token_type' => 'Bearer', 'access_token' => $token];
        });
    }
}
