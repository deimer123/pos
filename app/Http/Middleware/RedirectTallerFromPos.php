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

        $esTallerPuro = $user && $user->hasRole('taller') && ! $user->hasAnyRole(['admin_empresa', 'cajero', 'vendedor', 'digitador', 'mesero', 'cocina']);

        // El taller puro puede entrar al carrito ligado a una orden (/pos?taller=ID),
        // pero no al POS normal sin orden vinculada.
        if ($esTallerPuro && ! $request->query('taller')) {
            return redirect()->route('taller');
        }

        return $next($request);
    }
}
