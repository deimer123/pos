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

    private static function tablaDisponible(): bool
    {
        static $existe = null;

        return $existe ??= \Illuminate\Support\Facades\Schema::hasTable('pending_sync_operations');
    }
}
