<?php

namespace App\Http\Middleware;

use App\Support\PosEdition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo aplica en la edicion hibrida (Turion): si la terminal todavia no se
 * emparejo con ninguna empresa (tabla sync_state vacia), manda todo el
 * trafico a la pantalla de emparejamiento en vez de dejar que el usuario
 * choque contra el login sin tener ningun usuario todavia. En el droplet y
 * en la edicion Local (que se activa por codigo, no por emparejamiento --
 * ver EnsureLicenciaLocalActiva) es un no-op.
 */
class EnsureTerminalEmparejada
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PosEdition::esHibrida() || ! Schema::hasTable('sync_state')) {
            return $next($request);
        }

        if ($request->routeIs('emparejar') || str_starts_with($request->path(), 'emparejar')) {
            return $next($request);
        }

        $emparejada = DB::table('sync_state')->exists();

        if (! $emparejada) {
            return redirect()->route('emparejar');
        }

        return $next($request);
    }
}
