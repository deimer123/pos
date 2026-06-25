<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictMeseroFromPanel
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Mesero solo puede acceder al POS si está habilitado para mesas
        if ($user && $user->hasRole('mesero') && ! $user->hasAnyRole(['cajero', 'admin_empresa', 'vendedor'])) {
            // Solo permitir rutas de mesas y cocina
            $allowed = [
                'pos',
                'pos.mesa',
                'pos.mesa.cuenta',
                'cocina',
            ];
            if (! in_array($request->route()?->getName(), $allowed)) {
                abort(403, 'Acceso restringido para mesero.');
            }
        }

        return $next($request);
    }
}
