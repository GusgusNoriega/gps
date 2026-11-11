<?php

namespace App\Http\Controllers;

use App\Events\FleetUpdated;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\TrackedAsset;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $devices = Device::with(['latestPosition', 'currentAssignment.asset'])->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('devices.index', compact('devices', 'search'));
    }

    public function enrollments(): View
    {
        return view('devices.enrollments', ['enrollments' => Enrollment::orderByDesc('created_at')->paginate(20), 'settings' => Setting::current(), 'assets' => TrackedAsset::orderBy('name')->get()]);
    }

    public function approve(Request $request, Enrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate(['asset_id' => ['nullable', 'integer', 'exists:tracked_assets,id'], 'name' => ['sometimes', 'required', 'string', 'max:100']]);
        $device = $service->approve($enrollment, isset($data['asset_id']) ? (int) $data['asset_id'] : null, $data['name'] ?? null);

        return redirect()->route('devices.show', $device)->with('success', 'Dispositivo aprobado. La aplicación ya puede obtener su credencial.');
    }

    public function reject(Enrollment $enrollment): RedirectResponse
    {
        DB::transaction(function () use ($enrollment) {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($enrollment->status === 'pending', 409);
            $enrollment->update(['status' => 'rejected']);
            AuditLog::record('enrollment.rejected', $enrollment->name);
        });

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function show(Device $device): View
    {
        $device->load(['latestPosition', 'currentAssignment.asset', 'assignments.asset']);

        return view('devices.show', ['device' => $device, 'positions' => $device->positions()->latest('recorded_at')->limit(15)->get()]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $assetRequirement = $device->currentAssignment ? 'required' : 'nullable';
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'group_name' => ['nullable', 'string', 'max:60'], 'asset_name' => [$assetRequirement, 'string', 'max:100'], 'asset_type' => [$assetRequirement, Rule::in(['phone', 'vehicle'])], 'reference' => ['nullable', 'string', 'max:60']]);
        DB::transaction(function () use ($device, $data) {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $device->update(['name' => $data['name'], 'group_name' => $data['group_name'] ?? null]);
            $device->currentAssignment?->asset?->update(['name' => $data['asset_name'], 'type' => $data['asset_type'], 'reference' => $data['reference'] ?? null]);
            AuditLog::record('device.updated', $device->name);
            FleetUpdated::dispatch();
        });

        return back()->with('success', 'Cambios guardados.');
    }

    public function disable(Device $device): RedirectResponse
    {
        DB::transaction(function () use ($device) {
            $device = Device::query()->lockForUpdate()->findOrFail($device->id);
            $device->update(['status' => 'disabled']);
            $device->tokens()->delete();
            $device->assignments()->whereNull('ends_at')->update(['ends_at' => now()]);
            AuditLog::record('device.disabled', $device->name);
            FleetUpdated::dispatch();
        });

        return back()->with('success', 'Dispositivo desactivado y credenciales revocadas. El historial se conserva.');
    }
}
