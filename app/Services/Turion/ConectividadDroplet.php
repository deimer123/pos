<?php

namespace App\Services\Turion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Punto unico para saber si esta terminal puede hablar con el droplet
 * AHORA MISMO, y para llamar sus endpoints de forma sincronica.
 *
 * En el droplet mismo (MySQL) siempre esta "en linea" -- estas funciones
 * solo tienen sentido para una terminal de Turion (SQLite). Se usa para
 * decidir si el boton "Facturar" puede facturar en el momento (ver
 * FacturarEnLineaService) o si debe quedar bloqueado y solo permitir
 * guardar prefactura/orden/reserva para facturar despues.
 */
class ConectividadDroplet
{
    public static function estaEnLinea(): bool
    {
        if (DB::getDriverName() !== 'sqlite') {
            return true;
        }

        $syncState = self::syncState();

        if (! $syncState || ! $syncState->terminal_token || ! $syncState->servidor_url) {
            return false;
        }

        try {
            return Http::withToken($syncState->terminal_token)
                ->timeout(3)
                ->get(rtrim($syncState->servidor_url, '/').'/api/pairing/ping')
                ->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * POST autenticado e inmediato a un endpoint del droplet (a diferencia
     * de ColaSincronizacion::encolar(), que guarda la operacion para
     * subirla despues con "Subir"). Lanza una excepcion clara si la
     * terminal no esta emparejada o si el droplet responde con error.
     */
    public static function llamar(string $ruta, array $body, int $timeout = 30): array
    {
        $syncState = self::syncState();

        if (! $syncState || ! $syncState->terminal_token || ! $syncState->servidor_url) {
            throw new \RuntimeException('Esta terminal todavia no esta emparejada con el droplet.');
        }

        $body['uuid'] ??= (string) Str::uuid();

        $respuesta = Http::withToken($syncState->terminal_token)
            ->timeout($timeout)
            ->post(rtrim($syncState->servidor_url, '/').$ruta, $body);

        if (! $respuesta->successful()) {
            throw new \RuntimeException($respuesta->json('message') ?? "HTTP {$respuesta->status()}");
        }

        return $respuesta->json();
    }

    /**
     * Igual que sync_state en ColaSincronizacion::tablaDisponible(): la
     * tabla solo existe una vez que Turion corrio sus migraciones locales
     * (sqlite), asi que se verifica antes de consultarla en vez de dejar
     * que reviente con "table doesn't exist" en el arranque inicial.
     */
    private static function syncState(): ?object
    {
        if (! Schema::hasTable('sync_state')) {
            return null;
        }

        return DB::table('sync_state')->first();
    }
}
