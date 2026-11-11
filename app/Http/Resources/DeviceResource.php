<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $position = $this->latestPosition;
        $staleAfter = $request->attributes->get('stale_after', 120);
        $fresh = $position && $position->recorded_at->isAfter(now()->subSeconds($staleAfter));
        $status = $this->status === 'disabled' ? 'disabled' : (! $position ? 'no_location' : ($fresh ? 'online' : 'stale'));

        return [
            'id' => $this->id, 'name' => $this->name, 'type' => $this->type,
            'platform' => $this->platform, 'model' => $this->model, 'group' => $this->group_name,
            'status' => $status, 'administrative_status' => $this->status, 'tracking_state' => $this->tracking_state,
            'is_simulated' => $this->is_simulated, 'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'asset' => $this->currentAssignment?->asset?->only(['id', 'name', 'type', 'reference']),
            'position' => $position ? [
                'latitude' => $position->latitude, 'longitude' => $position->longitude,
                'accuracy' => $position->accuracy, 'speed' => $position->speed, 'heading' => $position->heading,
                'battery' => $position->battery, 'recorded_at' => $position->recorded_at->toIso8601String(),
                'received_at' => $position->received_at->toIso8601String(),
            ] : null,
            'detail_url' => route('devices.show', $this->id),
            'history_url' => route('history', ['device' => $this->id]),
        ];
    }
}
