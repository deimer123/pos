<?php

namespace App\Services\Turion;

use App\Models\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fusiona en la base de datos LOCAL de Turion la lista de ordenes de taller
 * activas que devuelve el droplet (ver PairingController::ordenesTaller()),
 * llamado al sincronizar (ver PosSyncCatalog). Misma logica de fusion por
 * servidor_id que PrefacturaSyncPull -- ver ese archivo para el porque.
 *
 * Usa DB::table() en vez de Eloquent a proposito: TallerOrden::booted()
 * encola "taller_crear" en CADA creacion (para las ordenes abiertas offline
 * en esta misma terminal) -- si se usara TallerOrden::create() aqui, una
 * orden que BAJA del droplet se encolaria para subirse de vuelta como si
 * fuera nueva, duplicandola alla en el siguiente "Subir".
 */
class TallerSyncPull
{
    public static function fusionar(array $remotas, int $empresaId): void
    {
        $idsRemotos = collect($remotas)->pluck('id')->all();

        DB::table('taller_ordenes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('servidor_id')
            ->whereNotIn('servidor_id', $idsRemotos)
            ->get()
            ->each(function ($orden) {
                DB::table('taller_repuestos')->where('orden_id', $orden->id)->delete();
                DB::table('taller_ordenes')->where('id', $orden->id)->delete();
            });

        foreach ($remotas as $remota) {
            try {
                self::fusionarUna($remota, $empresaId);
            } catch (\Throwable $e) {
                // Un dato incompleto/inconsistente de UNA orden no debe
                // tumbar la sincronizacion completa (ver el mismo criterio
                // en CatalogoImportador para filas de catalogo viejas).
                Log::warning("TallerSyncPull: orden remota #{$remota['id']} no se pudo fusionar: ".$e->getMessage());
            }
        }
    }

    private static function fusionarUna(array $remota, int $empresaId): void
    {
        $local = DB::table('taller_ordenes')
            ->where('empresa_id', $empresaId)
            ->where('servidor_id', $remota['id'])
            ->first();

        $datos = [
            'empresa_id' => $empresaId,
            'servidor_id' => $remota['id'],
            'cliente_id' => self::idSiExisteLocal(Actor::class, $remota['cliente_id'] ?? null),
            'cliente_nombre' => $remota['cliente_nombre'],
            'cliente_telefono' => $remota['cliente_telefono'] ?? null,
            'placa' => $remota['placa'],
            'marca' => $remota['marca'] ?? null,
            'modelo' => $remota['modelo'] ?? null,
            'color' => $remota['color'] ?? null,
            'km_ingreso' => $remota['km_ingreso'] ?? null,
            'diagnostico' => $remota['diagnostico'] ?? null,
            'observaciones' => $remota['observaciones'] ?? null,
            'estado' => $remota['estado'],
            'fecha_entrega_estimada' => $remota['fecha_entrega_estimada'] ?? null,
            'updated_at' => now(),
        ];

        if ($local) {
            $ordenId = $local->id;
            DB::table('taller_ordenes')->where('id', $ordenId)->update($datos);
        } else {
            // El numero de orden es secuencial por empresa Y unico
            // (empresa_id, numero_orden) -- el del droplet puede coincidir
            // con uno ya asignado localmente a una orden distinta creada
            // offline todavia sin subir, asi que se calcula uno propio en
            // vez de copiar el remoto (solo es un numero de referencia
            // visual, no un identificador real; ese es servidor_id).
            $datos['numero_orden'] = (int) DB::table('taller_ordenes')->where('empresa_id', $empresaId)->max('numero_orden') + 1;
            $datos['created_at'] = now();
            $ordenId = DB::table('taller_ordenes')->insertGetId($datos);
        }

        DB::table('taller_repuestos')->where('orden_id', $ordenId)->delete();

        foreach ($remota['items'] ?? [] as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio'];

            DB::table('taller_repuestos')->insert([
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
