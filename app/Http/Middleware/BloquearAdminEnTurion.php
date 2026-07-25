<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una terminal de Turion (base de datos LOCAL, SQLite) es solo para el
 * punto de venta -- no debe cargar nada de /admin. En el droplet (MySQL)
 * es un no-op.
 *
 * Se registra en el authMiddleware() del panel de Filament (ver
 * AdminPanelProvider), NO en el grupo 'web' de Kernel.php: Filament corre
 * su propio stack de middleware para /admin, independiente del de
 * Kernel.php. authMiddleware() solo se ejecuta ya autenticado, asi que
 * /admin/login (la unica puerta de autenticacion de toda la app, el panel
 * se registra con ->login() y nada mas) nunca pasa por aqui -- sigue
 * accesible sin que haga falta exceptuarla a mano.
 *
 * Los roles cuyo unico destino es /admin (super_admin, admin_empresa,
 * digitador puro) no tienen a donde ir en una terminal de venta -- en vez
 * de un loop de redirecciones, se les muestra un mensaje claro.
 */
class BloquearAdminEnTurion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (DB::getDriverName() !== 'sqlite') {
            return $next($request);
        }

        $user = auth()->user();

        $destino = match (true) {
            $user?->hasRole('taller') => 'taller',
            $user?->hasRole('recepcion') => 'hotel',
            $user?->hasRole('cocina') => 'cocina',
            $user?->hasAnyRole(['vendedor', 'cajero', 'mesero']) => 'pos',
            default => null,
        };

        if ($destino) {
            return redirect()->route($destino);
        }

        abort(403, 'Esta terminal es solo para el punto de venta. Para tareas administrativas, ingresa desde el navegador normal (no esta terminal).');
    }
}
