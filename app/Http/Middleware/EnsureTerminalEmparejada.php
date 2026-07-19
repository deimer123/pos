<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo aplica en la base de datos LOCAL de Turion (SQLite): si la terminal
 * todavia no se emparejo con ninguna empresa (tabla sync_state vacia),
 * manda todo el trafico a la pantalla de emparejamiento en vez de dejar
 * que el usuario choque contra el login sin tener ningun usuario todavia.
 * En el droplet (MySQL) la tabla sync_state ni siquiera existe -- no-op.
 */
class EnsureTerminalEmparejada
{
    public function handle(Request $request, Closure $next): Response
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('sync_state')) {
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
