<?php

namespace App\Services\Turion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encola una operacion pendiente de subir al droplet (boton "Subir" de
 * Turion). Solo hace algo quando corre contra la base de datos LOCAL
 * (SQLite) de una terminal -- en el droplet (MySQL) es un no-op, ya que
 * ahi no existe la tabla pending_sync_operations.
 *
 * Se usa desde los mismos servicios de negocio compartidos
 * (AgregarItemMesaService, GuardarOrdenTallerService, GuardarReservaService,
 * FacturarVentaService) para no tener que repetir la logica de encolado
 * en cada componente Livewire que los llama.
 */
class ColaSincronizacion
{
    public static function encolar(string $tipo, array $payload): ?string
    {
        if (DB::getDriverName() !== 'sqlite' || ! self::tablaDisponible()) {
            return null;
        }

        $uuid = (string) Str::uuid();

        DB::table('pending_sync_operations')->insert([
            'uuid' => $uuid,
            'tipo' => $tipo,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uuid;
    }

    /**
     * Resuelve el id que el droplet le asigno a un registro creado/editado
     * localmente (prefactura, orden de taller, reserva de hotel...) la
     * ULTIMA vez que se subio con exito -- ej. para saber si una prefactura
     * ya tiene contraparte en el droplet (actualizarla) o todavia no
     * (crearla alla por primera vez), o para poder decirle al droplet
     * "esta venta viene de tu prefactura #X, bórrala al facturar".
     *
     * Escanea en PHP (no con JSON del motor de la BD) porque el volumen
     * por terminal es bajo y sqlite/mysql no comparten la misma sintaxis
     * de funciones JSON.
     */
    public static function servidorIdSincronizado(string $tipo, string $campoLocalId, $localId): ?int
    {
        if (DB::getDriverName() !== 'sqlite' || ! self::tablaDisponible() || $localId === null) {
            return null;
        }

        $operacion = DB::table('pending_sync_operations')
            ->where('tipo', $tipo)
            ->where('estado', 'sincronizado')
            ->whereNotNull('resultado_id')
            ->orderByDesc('id')
            ->get(['payload', 'resultado_id'])
            ->first(function ($fila) use ($campoLocalId, $localId) {
                $payload = json_decode($fila->payload, true) ?? [];

                return isset($payload[$campoLocalId]) && (string) $payload[$campoLocalId] === (string) $localId;
            });

        return $operacion ? (int) $operacion->resultado_id : null;
    }

    private static function tablaDisponible(): bool
    {
        static $existe = null;

        return $existe ??= \Illuminate\Support\Facades\Schema::hasTable('pending_sync_operations');
    }
}
