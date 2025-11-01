<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictVendedorFromPanel
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Si es vendedor pero no digitador, bloquear acceso al panel
        if ($user && $user->hasRole('vendedor') && ! $user->hasRole('digitador')) {
            abort(403, 'Acceso no autorizado al panel.');
        }

        return $next($request);
    }
}
