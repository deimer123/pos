<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestrictCocinaAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('filament.admin.auth.login');
        }

        if (! $user->hasAnyRole(['cocina', 'admin_empresa', 'cajero'])) {
            abort(403, 'Acceso restringido a cocina.');
        }

        return $next($request);
    }
}
