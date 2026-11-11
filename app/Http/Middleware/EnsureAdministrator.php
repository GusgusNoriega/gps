<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        abort_unless($user instanceof User && $user->is_admin, 403, 'Acceso exclusivo del administrador.');

        return $next($request);
    }
}
