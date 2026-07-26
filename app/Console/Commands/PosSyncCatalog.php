<?php

namespace App\Console\Commands;

use App\Services\Turion\CatalogoImportador;
use App\Services\Turion\HotelSyncPull;
use App\Services\Turion\PrefacturaSyncPull;
use App\Services\Turion\TallerSyncPull;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Boton "Sincronizar" (y el chequeo automatico periodico que hace Turion en
 * segundo plano, ver src-tauri/src/lib.rs): refresca el catalogo local
 * (productos, precios, stock, mesas, habitaciones, usuarios) desde el
 * droplet, usando el token ya guardado al emparejar (no hace falta un
 * nuevo codigo). Tambien baja y fusiona las prefacturas, ordenes de taller
 * y reservas de hotel activas del droplet (ver PrefacturaSyncPull/
 * TallerSyncPull/HotelSyncPull) -- asi Turion se entera de lo que se creo
 * en el droplet (u otra terminal) y de lo que ya se cerro alla. No toca
 * pending_sync_operations -- lo que este pendiente de subir se sube por
 * separado, siempre de forma manual, con el boton "Subir" ("php artisan
 * pos:push").
 */
class PosSyncCatalog extends Command
{
    protected $signature = 'pos:sync-catalog
        {--if-due= : Solo sincroniza si pasaron al menos N horas desde la ultima vez (uso: chequeo automatico en segundo plano)}';

    protected $description = 'Refresca el catalogo local (productos, precios, mesas, habitaciones) desde el droplet.';

    public function handle(): int
    {
        $syncState = DB::table('sync_state')->first();

        if (! $syncState || ! $syncState->terminal_token) {
            if (! $this->option('if-due')) {
                $this->error('Esta terminal todavia no esta emparejada.');
            }

            return self::FAILURE;
        }

        if ($this->option('if-due') !== null && $syncState->ultima_sincronizacion_at) {
            $horas = (float) $this->option('if-due');
            $ultima = \Illuminate\Support\Carbon::parse($syncState->ultima_sincronizacion_at);

            if ($ultima->diffInMinutes(now()) < $horas * 60) {
                // Todavia no toca -- no-op silencioso, es un chequeo
                // automatico periodico, no una accion que pidio el cajero.
                return self::SUCCESS;
            }
        }

        $respuesta = Http::withToken($syncState->terminal_token)
            ->timeout(30)
            ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/catalogo');

        if (! $respuesta->successful()) {
            $this->error('No se pudo sincronizar: '.($respuesta->json('message') ?? $respuesta->status()));

            return self::FAILURE;
        }

        CatalogoImportador::sembrar($respuesta->json('catalogo'));

        $respuestaPrefacturas = Http::withToken($syncState->terminal_token)
            ->timeout(30)
            ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/prefacturas');

        if ($respuestaPrefacturas->successful() && $syncState->empresa_id) {
            PrefacturaSyncPull::fusionar($respuestaPrefacturas->json('prefacturas') ?? [], (int) $syncState->empresa_id);
        }

        if ($syncState->empresa_id) {
            $respuestaTaller = Http::withToken($syncState->terminal_token)
                ->timeout(30)
                ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/ordenes-taller');

            if ($respuestaTaller->successful()) {
                TallerSyncPull::fusionar($respuestaTaller->json('ordenes') ?? [], (int) $syncState->empresa_id);
            }

            $respuestaHotel = Http::withToken($syncState->terminal_token)
                ->timeout(30)
                ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/reservas-hotel');

            if ($respuestaHotel->successful()) {
                HotelSyncPull::fusionar($respuestaHotel->json('reservas') ?? [], (int) $syncState->empresa_id);
            }
        }

        DB::table('sync_state')->update(['ultima_sincronizacion_at' => now()]);

        $this->info('Catálogo sincronizado.');

        return self::SUCCESS;
    }
}
