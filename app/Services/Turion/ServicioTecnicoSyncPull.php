<?php

namespace App\Services\Turion;

use App\Models\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calco exacto de App\Services\Turion\TallerSyncPull -- ver ese archivo
 * para el detalle de cada decision (fusion por servidor_id, DB::table() en
 * vez de Eloquent, numero_orden recalculado localmente, etc.). Unica
 * diferencia: los campos propios de la orden (marca/modelo/imei_serial/
 * color/clave_desbloqueo en vez de placa/km_ingreso) y las tablas
 * servicio_tecnico_ordenes/servicio_tecnico_items.
 */
class ServicioTecnicoSyncPull
{
    public static function fusionar(array $remotas, int $empresaId): void
    {
        $idsRemotos = collect($remotas)->pluck('id')->all();

        DB::table('servicio_tecnico_ordenes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('servidor_id')
            ->whereNotIn('servidor_id', $idsRemotos)
            ->get()
            ->each(function ($orden) {
                DB::table('servicio_tecnico_items')->where('orden_id', $orden->id)->delete();
                DB::table('servicio_tecnico_ordenes')->where('id', $orden->id)->delete();
            });

        foreach ($remotas as $remota) {
            try {
                self::fusionarUna($remota, $empresaId);
            } catch (\Throwable $e) {
                Log::warning("ServicioTecnicoSyncPull: orden remota #{$remota['id']} no se pudo fusionar: ".$e->getMessage());
            }
        }
    }

    private static function fusionarUna(array $remota, int $empresaId): void
    {
        $local = DB::table('servicio_tecnico_ordenes')
            ->where('empresa_id', $empresaId)
            ->where('servidor_id', $remota['id'])
            ->first();

        $datos = [
            'empresa_id' => $empresaId,
            'servidor_id' => $remota['id'],
            'cliente_id' => self::idSiExisteLocal(Actor::class, $remota['cliente_id'] ?? null),
            'cliente_nombre' => $remota['cliente_nombre'],
            'cliente_telefono' => $remota['cliente_telefono'] ?? null,
            'marca' => $remota['marca'] ?? null,
            'modelo' => $remota['modelo'] ?? null,
            'imei_serial' => $remota['imei_serial'] ?? null,
            'color' => $remota['color'] ?? null,
            'clave_desbloqueo' => $remota['clave_desbloqueo'] ?? null,
            'diagnostico' => $remota['diagnostico'] ?? null,
            'observaciones' => $remota['observaciones'] ?? null,
            'estado' => $remota['estado'],
            'fecha_entrega_estimada' => $remota['fecha_entrega_estimada'] ?? null,
            'updated_at' => now(),
        ];

        if ($local) {
            $ordenId = $local->id;
            DB::table('servicio_tecnico_ordenes')->where('id', $ordenId)->update($datos);
        } else {
            $datos['numero_orden'] = (int) DB::table('servicio_tecnico_ordenes')->where('empresa_id', $empresaId)->max('numero_orden') + 1;
            $datos['created_at'] = now();
            $ordenId = DB::table('servicio_tecnico_ordenes')->insertGetId($datos);
        }

        DB::table('servicio_tecnico_items')->where('orden_id', $ordenId)->delete();

        foreach ($remota['items'] ?? [] as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio'];

            DB::table('servicio_tecnico_items')->insert([
                'orden_id' => $ordenId,
                'producto_id' => is_numeric($item['producto_id'] ?? null) ? (int) $item['producto_id'] : null,
                'descripcion' => $item['nombre'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cantidad, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private static function idSiExisteLocal(string $modelo, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return $modelo::whereKey($id)->exists() ? $id : null;
    }
}
