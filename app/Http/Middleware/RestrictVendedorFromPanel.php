<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictVendedorFromPanel
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->is('admin/login') ||
            $request->is('logout')
        ) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasAnyRole(['super_admin', 'admin_empresa', 'digitador'])) {
            return $next($request);
        }

        // Lista blanca en vez de lista negra: antes solo se bloqueaba a
        // vendedor/cajero explicitamente, asi que cualquier otro rol nuevo
        // (taller, recepcion, mesero, cocina) pasaba sin ningun control --
        // eso permitia que, por ejemplo, un usuario de taller terminara
        // viendo el dashboard completo de /admin si por cualquier motivo
        // llegaba a esa URL (ej. la redireccion "intended" de Laravel
        // despues del login). Ahora solo pasan los roles explicitamente
        // permitidos arriba; todo lo demas queda bloqueado por defecto.
        abort(403, 'Acceso no autorizado al panel.');
    }
}
