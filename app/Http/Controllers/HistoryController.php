<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Position;
use App\Models\TrackedAsset;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('history', ['devices' => Device::orderBy('name')->get(), 'assets' => TrackedAsset::orderBy('name')->get(), 'selectedDevice' => $request->integer('device'), 'timezone' => config('tracking.timezone')]);
    }

    public function data(Request $request): JsonResponse
    {
        $data = $request->validate(['device_id' => ['nullable', 'required_without:asset_id', 'integer', 'exists:devices,id'], 'asset_id' => ['nullable', 'required_without:device_id', 'integer', 'exists:tracked_assets,id'], 'from' => ['required', 'date_format:Y-m-d\TH:i'], 'to' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:from']]);
        $from = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['from'], config('tracking.timezone'))->utc();
        $to = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['to'], config('tracking.timezone'))->utc()->endOfMinute();
        abort_if($from->diffInDays($to) > 7, 422, 'Consulta como máximo siete días a la vez.');
        $query = Position::whereBetween('recorded_at', [$from, $to])->when($data['device_id'] ?? null, fn ($query, $id) => $query->where('device_id', $id))->when($data['asset_id'] ?? null, fn ($query, $id) => $query->whereIn('device_assignment_id', DeviceAssignment::where('tracked_asset_id', $id)->select('id')));
        $total = (clone $query)->count();
        $points = $query->orderBy('recorded_at')->orderBy('id')->limit(config('tracking.history_limit'))->get();
        $segments = [];
        $segment = [];
        $previous = null;
        foreach ($points as $point) {
            if (! $point->is_usable) {
                if (count($segment)) {
                    $segments[] = $segment;
                }
                $segment = [];
                $previous = null;

                continue;
            }
            if ($previous && ($previous->device_id !== $point->device_id || $previous->device_assignment_id !== $point->device_assignment_id || $previous->recorded_at->diffInSeconds($point->recorded_at) > 300)) {
                if (count($segment)) {
                    $segments[] = $segment;
                }
                $segment = [];
            }
            $segment[] = [$point->latitude, $point->longitude];
            $previous = $point;
        }
        if (count($segment)) {
            $segments[] = $segment;
        }

        return response()->json(['total' => $total, 'returned' => $points->count(), 'truncated' => $total > $points->count(), 'segments' => $segments, 'points' => $points->map(fn (Position $point) => ['latitude' => $point->latitude, 'longitude' => $point->longitude, 'recorded_at' => $point->recorded_at->toIso8601String(), 'accuracy' => $point->accuracy, 'speed' => $point->speed, 'is_usable' => $point->is_usable, 'device_id' => $point->device_id])->values()])->header('Cache-Control', 'no-store');
    }
}
