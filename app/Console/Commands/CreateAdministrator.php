<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdministrator extends Command
{
    protected $signature = 'gps:admin {email} {--name=Administrador} {--generate : Genera una contraseña aleatoria y guarda el acceso local} {--reset : Restablece un administrador existente}';

    protected $description = 'Crea o recupera el acceso administrativo desde el servidor';

    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('Correo no válido.');

            return self::FAILURE;
        }
        $existing = User::where('email', $email)->first();
        if ($existing && ! $this->option('reset')) {
            $this->error('La cuenta ya existe. Utiliza --reset para recuperar su acceso.');

            return self::FAILURE;
        }
        $password = $this->option('generate') ? 'Gps9!'.Str::random(20) : (string) $this->secret('Contraseña (mínimo 12 caracteres, letras y números)');
        if (strlen($password) < 12 || ! preg_match('/[a-zA-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            $this->error('La contraseña no cumple los requisitos.');

            return self::FAILURE;
        }
        $user = $existing ?? new User;
        $user->forceFill(['email' => $email, 'name' => $this->option('name'), 'password' => Hash::make($password), 'is_admin' => true, 'remember_token' => null])->save();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        if ($this->option('generate')) {
            Storage::disk('local')->put('ACCESO_LOCAL.txt', "NEXO GPS — ACCESO ADMINISTRATIVO\n\nDirección: ".config('app.url')."\nCorreo: {$email}\nContraseña: {$password}\n\nCambia la contraseña en Configuración. Este archivo es privado y no se incluye en Git.\n");
            $this->info('Acceso guardado en storage/app/private/ACCESO_LOCAL.txt');
        }
        $this->info('Administrador preparado: '.$email);

        return self::SUCCESS;
    }
}
