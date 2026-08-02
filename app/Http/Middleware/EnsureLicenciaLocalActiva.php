<?php

namespace App\Http\Middleware;

use App\Models\ActivacionLocal;
use App\Support\PosEdition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo aplica en la edicion Local: si esta instalacion todavia no se
 * activo con un codigo valido (ver App\Services\LocalLicense), manda todo
 * el trafico a la pantalla de activacion en vez de dejar que el usuario
 * choque contra el login sin tener ningun usuario todavia. En el droplet
 * y en la edicion hibrida (Turion, que se activa por emparejamiento, no
 * por codigo -- ver EnsureTerminalEmparejada) es un no-op.
 */
class EnsureLicenciaLocalActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PosEdition::esLocal() || ! Schema::hasTable('activacion_local')) {
            return $next($request);
        }

        if ($request->routeIs('activar') || str_starts_with($request->path(), 'activar')) {
            return $next($request);
        }

        // Un terminal cliente que se conecta por primera vez todavia no
        // tiene fila propia en activacion_local -- si no se dejara pasar
        // esto, quedaria atrapado en un redirect a /activar (que ademas es
        // para activar un SERVIDOR nuevo, no un terminal).
        if (str_starts_with($request->path(), 'emparejar-terminal')) {
            return $next($request);
        }

        if (! ActivacionLocal::activa()) {
            return redirect()->route('activar');
        }

        return $next($request);
    }
}
