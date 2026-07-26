<?php

namespace App\Services\Turion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fusiona en la base de datos LOCAL de Turion la lista de ordenes de mesa
 * activas (mesas ocupadas Y domicilios, que son el mismo modelo OrdenMesa
 * con tipo_pedido='domicilio') que devuelve el droplet (ver
 * PairingController::ordenesMesaActivas()), llamado al sincronizar.
 *
 * A diferencia de taller/hotel, una orden de mesa NO tiene su propio
 * servidor_id: se identifica por mesa_id (las mesas SI vienen en el
 * catalogo con el mismo id en ambos lados), asumiendo que solo hay UNA
 * orden activa por mesa a la vez (mismo invariante que ya asume
 * AgregarItemMesaService al agregar items).
 *
 * Usa DB::table() en vez de Eloquent por la misma razon que TallerSyncPull/
 * HotelSyncPull: evitar disparar eventos de modelo que pudieran re-encolar
 * esto de vuelta hacia el droplet.
 */
class OrdenMesaSyncPull
{
    public static function fusionar(array $remotas, int $empresaId): void
    {
        $mesaIdsRemotos = collect($remotas)->pluck('mesa_id')->all();

        // Mesas cuya orden activa localmente ya no aparece como activa en
        // el droplet -- se facturo o se libero directo alla (u otra
        // terminal). Mesa.estado ya se corrige solo via el catalogo
        // (mesas SI esta en CatalogoImportador::TABLAS); aqui solo hace
        // falta limpiar la orden/items localmente para que no quede
        // mostrando contenido viejo.
        DB::table('ordenes_mesas')
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ['abierta', 'en_preparacion', 'lista'])
            ->whereNotIn('mesa_id', $mesaIdsRemotos)
            ->get()
            ->each(function ($orden) {
                DB::table('orden_mesa_items')->where('orden_mesa_id', $orden->id)->delete();
                DB::table('ordenes_mesas')->where('id', $orden->id)->delete();
            });

        foreach ($remotas as $remota) {
            try {
                self::fusionarUna($remota, $empresaId);
            } catch (\Throwable $e) {
                Log::warning("OrdenMesaSyncPull: orden remota de mesa #{$remota['mesa_id']} no se pudo fusionar: ".$e->getMessage());
            }
        }
    }

    private static function fusionarUna(array $remota, int $empresaId): void
    {
        $local = DB::table('ordenes_mesas')
            ->where('empresa_id', $empresaId)
            ->where('mesa_id', $remota['mesa_id'])
            ->whereIn('estado', ['abierta', 'en_preparacion', 'lista'])
            ->first();

        $datos = [
            'empresa_id' => $empresaId,
            'mesa_id' => $remota['mesa_id'],
            'cliente_id' => $remota['cliente_id'] ?? null,
            'estado' => $remota['estado'],
            'subtotal' => $remota['subtotal'] ?? 0,
            'impuestos' => $remota['impuestos'] ?? 0,
            'descuento' => $remota['descuento'] ?? 0,
            'total' => $remota['total'] ?? 0,
            'observaciones' => $remota['observaciones'] ?? null,
            'tipo_pedido' => $remota['tipo_pedido'] ?? 'mesa',
            'costo_empaque' => $remota['costo_empaque'] ?? 0,
            'dom_nombre' => $remota['dom_nombre'] ?? null,
            'dom_telefono' => $remota['dom_telefono'] ?? null,
            'dom_direccion' => $remota['dom_direccion'] ?? null,
            'dom_observaciones' => $remota['dom_observaciones'] ?? null,
            'dom_costo_domicilio' => $remota['dom_costo_domicilio'] ?? 0,
            'dom_costo_desechables' => $remota['dom_costo_desechables'] ?? 0,
            'numero_cocina_dia' => $remota['numero_cocina_dia'] ?? null,
            'entregada' => $remota['entregada'] ?? false,
            'updated_at' => now(),
        ];

        if ($local) {
            $ordenId = $local->id;
            DB::table('ordenes_mesas')->where('id', $ordenId)->update($datos);
        } else {
            $datos['created_at'] = now();
            $datos['abierta_en'] = now();
            $ordenId = DB::table('ordenes_mesas')->insertGetId($datos);
        }

        DB::table('orden_mesa_items')->where('orden_mesa_id', $ordenId)->delete();

        foreach ($remota['items'] ?? [] as $item) {
            DB::table('orden_mesa_items')->insert([
                'empresa_id' => $empresaId,
                'orden_mesa_id' => $ordenId,
                'product_id' => $item['product_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal' => $item['subtotal'],
                'estado_cocina' => $item['estado_cocina'] ?? 'pendiente',
                'nota_cocina' => $item['nota_cocina'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // La mesa fisica ya viene del catalogo (mismo id en ambos lados),
        // pero su "estado" se pisa completo en cada Sincronizar -- si el
        // catalogo se bajo ANTES de que esta orden llegara aqui, hay que
        // marcarla ocupada tambien, para no mostrar la mesa en verde con
        // una orden activa adentro.
        DB::table('mesas')->where('id', $remota['mesa_id'])->where('empresa_id', $empresaId)
            ->where('estado', 'libre')
            ->update(['estado' => 'ocupada']);
    }
}
