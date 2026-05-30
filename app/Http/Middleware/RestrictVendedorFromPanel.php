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

        if ($user->hasAnyRole(['vendedor', 'cajero'])) {
            abort(403, 'Acceso no autorizado al panel.');
        }

        return $next($request);
    }
}
