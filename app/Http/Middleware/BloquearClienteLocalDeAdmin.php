<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un cliente de la edicion Local (tipo_edicion = 'local') no tiene cuenta
 * usable en el droplet: esa fila de User solo existe como ancla para
 * emitirle licencias (ver App\Filament\Pages\EmitirLicenciaLocal), su
 * unica interfaz real es la app de escritorio activada con codigo.
 *
 * OJO: no alcanza con User::puedeIngresarPorPlan() + EnsureEmpresaActiva
 * (grupo 'web' de Kernel.php) -- Filament arma su PROPIO stack de
 * middleware para /admin, independiente del de Kernel.php (mismo motivo
 * que BloquearAdminEnTurion esta registrado aca y no alla), asi que ese
 * gate nunca llegaba a proteger el panel. Se registra en el
 * authMiddleware() del panel (ver AdminPanelProvider).
 */
class BloquearClienteLocalDeAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user?->esClienteLocal()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors([
                    'email' => 'Esta cuenta no tiene acceso al panel del droplet: es un cliente de la edición Local. Ingresa desde la aplicación de escritorio activada con tu código de licencia.',
                ]);
        }

        return $next($request);
    }
}
