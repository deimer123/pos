<?php

namespace App\Services\ServicioTecnico;

use App\Models\Product;
use App\Models\ServicioTecnicoItem;
use App\Models\ServicioTecnicoOrden;
use App\Services\Turion\ColaSincronizacion;

/**
 * Calco exacto de App\Services\Taller\GuardarOrdenTallerService para
 * Servicio Tecnico -- ver ese archivo para el detalle de por que existe
 * (reutilizado tanto desde CarritoVenta::sincronizarCarritoConOrdenServicioTecnico()
 * online como desde la sincronizacion offline, ver
 * PosSyncController::servicioTecnicoItem()).
 */
class GuardarOrdenServicioTecnicoService
{
    /**
     * @param  array<int, array<string, mixed>>  $carrito
     */
    public function sincronizarItems(int $ordenId, int $empresaId, array $carrito): ServicioTecnicoOrden
    {
        $orden = ServicioTecnicoOrden::where('id', $ordenId)
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        ServicioTecnicoItem::where('orden_id', $orden->id)->delete();

        foreach ($carrito as $item) {
            $precio = (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant = (float) ($item['cantidad'] ?? 1);

            ServicioTecnicoItem::create([
                'orden_id' => $orden->id,
                'producto_id' => is_numeric($item['id_producto']) ? (int) $item['id_producto'] : null,
                'descripcion' => $item['nombre'] ?? 'Sin descripción',
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cant, 2),
            ]);
        }

        ColaSincronizacion::encolar('servicio_tecnico_item', [
            'servicio_tecnico_orden_id' => $ordenId,
            'items' => array_map(fn ($item) => [
                'id_producto' => $item['id_producto'],
                'nombre' => $item['nombre'] ?? 'Sin descripción',
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'precio' => (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0),
            ], $carrito),
        ]);

        return $orden;
    }

    /**
     * Reconstruye un array con la misma forma de CarritoVenta::$carrito a
     * partir de los ServicioTecnicoItem actuales de la orden, para poder
     * facturar sin depender de que el cliente offline mande los precios.
     */
    public function cargarCarritoDesdeOrden(int $ordenId, int $empresaId): array
    {
        $orden = ServicioTecnicoOrden::where('id', $ordenId)
            ->where('empresa_id', $empresaId)
            ->with('items')
            ->first();

        if (! $orden) {
            return [];
        }

        $carrito = [];

        foreach ($orden->items as $item) {
            $precio = (float) $item->precio_unitario;
            $cant = (float) $item->cantidad;

            if (! $item->producto_id) {
                continue;
            }

            $producto = Product::where('id_producto', $item->producto_id)
                ->where('empresa_id', $empresaId)
                ->first();

            if (! $producto) {
                continue;
            }

            $key = (string) $item->producto_id;

            $carrito[$key] = [
                'uuid' => $key,
                'id_producto' => $producto->id_producto,
                'nombre' => $item->descripcion ?: $producto->descripcion_larga,
                'cantidad' => $cant,
                'precio' => $precio,
                'nuevo_precio' => $precio,
                'descuento' => 0,
                'id_unidad_de_medida' => (int) ($producto->id_unidad_de_medida ?? 1),
                'vende_por' => (string) ($producto->vende_por ?? 'unidad'),
                'permite_fraccion' => (bool) ($producto->permite_fraccion ?? false),
                'permite_decimal' => $producto->permiteCantidadDecimal(),
            ];
        }

        return $carrito;
    }
}
