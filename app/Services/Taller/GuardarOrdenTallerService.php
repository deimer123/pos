<?php

namespace App\Services\Taller;

use App\Models\Product;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use App\Services\Turion\ColaSincronizacion;

/**
 * Logica de repuestos de una orden de taller extraida de
 * App\Livewire\CarritoVenta::sincronizarCarritoConOrdenTaller() /
 * cargarOrdenTaller() (extraccion pura, sin cambiar el comportamiento
 * online), para reutilizarla desde la sincronizacion de pedidos guardados
 * offline. Solo cubre ordenes que ya existian -- crear una orden de taller
 * nueva estando offline se encola por separado (ver TallerOrden::booted()).
 */
class GuardarOrdenTallerService
{
    /**
     * Mismo comportamiento que CarritoVenta::sincronizarCarritoConOrdenTaller():
     * borra los repuestos actuales de la orden y los vuelve a crear desde
     * el carrito completo (no es incremental).
     *
     * @param  array<int, array<string, mixed>>  $carrito
     */
    public function sincronizarItems(int $tallerOrdenId, int $empresaId, array $carrito): TallerOrden
    {
        $orden = TallerOrden::where('id', $tallerOrdenId)
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        TallerRepuesto::where('orden_id', $orden->id)->delete();

        foreach ($carrito as $item) {
            $precio = (float) ($item['nuevo_precio'] ?? $item['precio'] ?? 0);
            $cant = (float) ($item['cantidad'] ?? 1);

            TallerRepuesto::create([
                'orden_id' => $orden->id,
                'producto_id' => is_numeric($item['id_producto']) ? (int) $item['id_producto'] : null,
                'descripcion' => $item['nombre'] ?? 'Sin descripción',
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'subtotal' => round($precio * $cant, 2),
            ]);
        }

        ColaSincronizacion::encolar('taller_item', [
            'taller_orden_id' => $tallerOrdenId,
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
     * partir de los TallerRepuesto actuales de la orden (igual que
     * CarritoVenta::cargarOrdenTaller()), para poder facturar sin depender
     * de que el cliente offline mande los precios.
     */
    public function cargarCarritoDesdeOrden(int $tallerOrdenId, int $empresaId): array
    {
        $orden = TallerOrden::where('id', $tallerOrdenId)
            ->where('empresa_id', $empresaId)
            ->with('repuestos')
            ->first();

        if (! $orden) {
            return [];
        }

        $carrito = [];

        foreach ($orden->repuestos as $rep) {
            $precio = (float) $rep->precio_unitario;
            $cant = (float) $rep->cantidad;

            if (! $rep->producto_id) {
                continue;
            }

            $producto = Product::where('id_producto', $rep->producto_id)
                ->where('empresa_id', $empresaId)
                ->first();

            if (! $producto) {
                continue;
            }

            $key = (string) $rep->producto_id;

            $carrito[$key] = [
                'uuid' => $key,
                'id_producto' => $producto->id_producto,
                'nombre' => $rep->descripcion ?: $producto->descripcion_larga,
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
