<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function store(Request $request, EnrollmentService $service): JsonResponse
    {
        abort_unless(Setting::current()->enrollment_open, 403, 'Las nuevas vinculaciones están cerradas.');
        $data = $request->validate(['installation_id' => ['required', 'uuid', 'unique:enrollments,installation_id', 'unique:devices,installation_id'], 'name' => ['required', 'string', 'max:100'], 'platform' => ['required', Rule::in(['android', 'ios', 'test'])], 'model' => ['nullable', 'string', 'max:100']]);

        return response()->json($service->request($data), 201)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->authenticate($request, $enrollment);

        return response()->json(['status' => $enrollment->expires_at->isPast() && $enrollment->status !== 'exchanged' ? 'expired' : $enrollment->status, 'expires_at' => $enrollment->expires_at->toIso8601String(), 'poll_after_seconds' => 10])->header('Cache-Control', 'no-store');
    }

    public function exchange(Request $request, Enrollment $enrollment, EnrollmentService $service): JsonResponse
    {
        $this->authenticate($request, $enrollment);

        return response()->json($service->exchange($enrollment))->header('Cache-Control', 'no-store');
    }

    private function authenticate(Request $request, Enrollment $enrollment): void
    {
        abort_unless($request->bearerToken() && hash_equals($enrollment->secret_hash, hash('sha256', $request->bearerToken())), 401, 'Credencial temporal no válida.');
    }
}
