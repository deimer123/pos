<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictVendedorFromPanel
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ permitir rutas login/logout
        if (
            $request->is('admin/login') ||
            $request->is('logout')
        ) {
            return $next($request);
        }

        $user = Auth::user();

        // ✅ si no hay usuario, permitir continuar
        if (! $user) {
            return $next($request);
        }

        $esVendedor = $user->hasRole('vendedor');

        $esCajero = $user->hasRole('cajero');

        $esDigitador = $user->hasRole('digitador');

        // ❌ bloquear SOLO vendedor/cajero puros
        if (
            ($esVendedor || $esCajero) &&
            ! $esDigitador
        ) {
            abort(403, 'Acceso no autorizado al panel.');
        }

        return $next($request);
    }
}