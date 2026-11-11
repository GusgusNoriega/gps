<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings', ['settings' => Setting::current(), 'audit' => AuditLog::latest()->limit(15)->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enrollment_open' => ['required', 'boolean'], 'moving_interval' => ['required', 'integer', 'between:5,300'], 'stationary_interval' => ['required', 'integer', 'gte:moving_interval', 'max:3600'], 'stale_after' => ['required', 'integer', 'gte:stationary_interval', 'max:7200']]);
        Setting::current()->update($data);
        AuditLog::record('settings.updated', 'Configuración de seguimiento');

        return back()->with('success', 'Configuración guardada. Los dispositivos la recibirán en su próxima consulta.');
    }

    public function prepareServer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_url' => ['required', 'string', 'max:255', 'url:https', 'regex:~\Ahttps://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?/?\z~i'],
        ], [
            'server_url.required' => 'Introduce el dominio del servidor.',
            'server_url.url' => 'Usa una dirección HTTPS, por ejemplo https://gps.tudominio.com.',
            'server_url.regex' => 'Usa únicamente https:// y el dominio, sin rutas, puertos, credenciales ni parámetros.',
        ]);
        $serverUrl = strtolower(rtrim($data['server_url'], '/'));

        return redirect()->to(route('settings').'#server-configuration')->with('server_setup', [
            'url' => $serverUrl,
            'api_url' => $serverUrl.'/api/v1',
            'environment' => implode("\n", [
                'APP_ENV=production',
                'APP_DEBUG=false',
                'APP_URL='.$serverUrl,
                'SESSION_SECURE_COOKIE=true',
                'BROADCAST_CONNECTION=null',
                'QUEUE_CONNECTION=sync',
            ]),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->session()->regenerate();
        AuditLog::record('admin.password_changed', $request->user()->email);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
