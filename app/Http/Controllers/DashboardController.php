<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\DemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }

    public function snapshot(Request $request): JsonResponse
    {
        $request->attributes->set('stale_after', Setting::current()->stale_after);
        $devices = Device::with(['latestPosition', 'currentAssignment.asset'])->orderBy('name')->get();

        return response()->json(['devices' => DeviceResource::collection($devices)->resolve($request), 'pending' => Enrollment::where('status', 'pending')->where('expires_at', '>', now())->count(), 'server_time' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }

    public function demo(Request $request, DemoService $service): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['seed', 'advance', 'clear'])]]);
        $service->{$data['action']}();

        return response()->json(['ok' => true]);
    }
}
