<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoloVendedor
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user()->hasRole('vendedor')) {
            abort(403, 'Acceso denegado: Solo vendedores pueden acceder.');
        }

        return $next($request);
    }
}

