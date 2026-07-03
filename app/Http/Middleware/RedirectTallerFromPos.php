<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectTallerFromPos
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->hasRole('taller') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'digitador', 'mesero', 'cocina'])) {
            return redirect()->route('taller');
        }

        return $next($request);
    }
}
