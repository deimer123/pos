<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('super_admin')) {
            return $next($request);
        }

        if (! $user->puedeIngresarPorPlan()) {
            // Mensaje especifico para clientes Local: no es un problema de
            // plan vencido, esa cuenta simplemente no tiene acceso al
            // droplet -- su interfaz es la app de escritorio activada con
            // codigo, no este panel.
            $mensaje = $user->esClienteLocal()
                ? 'Esta cuenta no tiene acceso al panel del droplet: es un cliente de la edicion Local. Ingresa desde la aplicacion de escritorio activada con tu codigo de licencia.'
                : 'La empresa esta inactiva o el plan se encuentra vencido. Contacta al administrador del sistema.';

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => $mensaje]);
        }

        return $next($request);
    }
}
