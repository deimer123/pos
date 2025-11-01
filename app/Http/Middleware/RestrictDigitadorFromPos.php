<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictDigitadorFromPos
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Bloquear acceso si es digitador (con o sin otros roles)
        if ($user && $user->hasRole('digitador') && ! $user->hasRole('vendedor')) {
            abort(403, 'Acceso denegado al Punto de Venta.');
        }

        return $next($request);
    }
}
