<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectCocinaFromPos
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->hasRole('cocina') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'mesero', 'digitador'])) {
            return redirect()->route('cocina');
        }

        return $next($request);
    }
}
