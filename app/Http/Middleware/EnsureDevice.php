<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();
        abort_unless($device instanceof Device && $device->currentAccessToken() instanceof PersonalAccessToken, 403, 'Se requiere una credencial de dispositivo.');
        abort_unless($device->status === 'active' && $device->tokenCan('telemetry:write'), 403, 'Dispositivo desactivado.');

        return $next($request);
    }
}
