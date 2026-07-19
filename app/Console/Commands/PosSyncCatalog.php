<?php

namespace App\Console\Commands;

use App\Services\Turion\CatalogoImportador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Boton "Sincronizar": refresca el catalogo local (productos, precios,
 * stock, mesas, habitaciones, usuarios) desde el droplet, usando el token
 * ya guardado al emparejar (no hace falta un nuevo codigo). No toca
 * pending_sync_operations -- lo que este pendiente de subir se sube por
 * separado con el boton "Subir" ("php artisan pos:push").
 */
class PosSyncCatalog extends Command
{
    protected $signature = 'pos:sync-catalog';

    protected $description = 'Refresca el catalogo local (productos, precios, mesas, habitaciones) desde el droplet.';

    public function handle(): int
    {
        $syncState = DB::table('sync_state')->first();

        if (! $syncState || ! $syncState->terminal_token) {
            $this->error('Esta terminal todavia no esta emparejada.');

            return self::FAILURE;
        }

        $respuesta = Http::withToken($syncState->terminal_token)
            ->timeout(30)
            ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/catalogo');

        if (! $respuesta->successful()) {
            $this->error('No se pudo sincronizar: '.($respuesta->json('message') ?? $respuesta->status()));

            return self::FAILURE;
        }

        CatalogoImportador::sembrar($respuesta->json('catalogo'));

        DB::table('sync_state')->update(['ultima_sincronizacion_at' => now()]);

        $this->info('Catálogo sincronizado.');

        return self::SUCCESS;
    }
}
