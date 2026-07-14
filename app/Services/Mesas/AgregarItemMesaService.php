<?php

namespace App\Services\Mesas;

use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\OrdenMesaItem;
use App\Models\Product;

/**
 * Logica de "agregar producto a la cuenta de una mesa" extraida de
 * App\Livewire\CarritoVenta (guardarProductoEnOrdenMesa,
 * obtenerOrdenMesaActiva, recalcularTotalOrden, cargarCarritoDesdeOrdenMesa
 * — extraccion pura, sin cambiar el comportamiento online), para
 * reutilizarla desde la sincronizacion de pedidos guardados offline.
 */
class AgregarItemMesaService
{
    public function obtenerOrdenMesaActiva(int $mesaId, int $empresaId, int $usuarioId): OrdenMesa
    {
        $existente = OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->latest()
            ->first();

        if ($existente) {
            return $existente;
        }

        return OrdenMesa::create([
            'empresa_id' => $empresaId,
            'mesa_id' => $mesaId,
            'estado' => 'abierta',
            'usuario_id' => $usuarioId,
            'abierta_en' => now(),
            'total' => 0,
        ]);
    }

    public function recalcularTotalOrden(int $ordenId): void
    {
        $total = OrdenMesaItem::where('orden_mesa_id', $ordenId)->sum('subtotal');
        OrdenMesa::where('id', $ordenId)->update(['total' => $total]);
    }

    /**
     * Mismo comportamiento que CarritoVenta::guardarProductoEnOrdenMesa():
     * fija la cantidad del item pendiente al valor dado (no la suma).
     * Usado por el flujo online, donde $cantidad ya es el total acumulado
     * en $carrito.
     */
    public function establecerCantidadItem(Product $producto, float $cantidad, int $mesaId, int $empresaId, int $usuarioId): OrdenMesaItem
    {
        $orden = $this->obtenerOrdenMesaActiva($mesaId, $empresaId, $usuarioId);

        $existente = OrdenMesaItem::where('orden_mesa_id', $orden->id)
            ->where('product_id', $producto->id)
            ->whereIn('estado_cocina', ['pendiente', 'enviado', 'preparando'])
            ->orderByDesc('id')
            ->first();

        if ($existente && $existente->estado_cocina === 'pendiente') {
            $existente->cantidad = $cantidad;
            $existente->subtotal = round($existente->precio_unitario * $cantidad, 2);
            $existente->save();
            $item = $existente;
        } else {
            $item = OrdenMesaItem::create([
                'empresa_id' => $empresaId,
                'orden_mesa_id' => $orden->id,
                'product_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => (float) $producto->precio_venta1,
                'subtotal' => round((float) $producto->precio_venta1 * $cantidad, 2),
                'estado_cocina' => 'pendiente',
                'requiere_cocina' => true,
            ]);
        }

        Mesa::where('id', $mesaId)->update(['estado' => 'ocupada']);
        $this->recalcularTotalOrden($orden->id);

        return $item;
    }

    /**
     * Para sincronizar pedidos guardados offline: cada click en "Agregar"
     * sin conexion se encola como "+1 unidad" (no como un total absoluto,
     * porque el dispositivo offline no conoce el total acumulado real del
     * servidor). Por eso aqui se SUMA a la cantidad existente, en vez de
     * fijarla como hace establecerCantidadItem().
     */
    public function incrementarItem(int $idProducto, float $cantidadDelta, int $mesaId, int $empresaId, int $usuarioId): OrdenMesaItem
    {
        $producto = Product::where('empresa_id', $empresaId)
            ->where('id_producto', $idProducto)
            ->firstOrFail();

        $orden = $this->obtenerOrdenMesaActiva($mesaId, $empresaId, $usuarioId);

        $existente = OrdenMesaItem::where('orden_mesa_id', $orden->id)
            ->where('product_id', $producto->id)
            ->whereIn('estado_cocina', ['pendiente', 'enviado', 'preparando'])
            ->orderByDesc('id')
            ->first();

        if ($existente && $existente->estado_cocina === 'pendiente') {
            $existente->cantidad = (float) $existente->cantidad + $cantidadDelta;
            $existente->subtotal = round($existente->precio_unitario * $existente->cantidad, 2);
            $existente->save();
            $item = $existente;
        } else {
            $item = OrdenMesaItem::create([
                'empresa_id' => $empresaId,
                'orden_mesa_id' => $orden->id,
                'product_id' => $producto->id,
                'cantidad' => $cantidadDelta,
                'precio_unitario' => (float) $producto->precio_venta1,
                'subtotal' => round((float) $producto->precio_venta1 * $cantidadDelta, 2),
                'estado_cocina' => 'pendiente',
                'requiere_cocina' => true,
            ]);
        }

        Mesa::where('id', $mesaId)->update(['estado' => 'ocupada']);
        $this->recalcularTotalOrden($orden->id);

        return $item;
    }

    /**
     * Reconstruye un array con la misma forma de CarritoVenta::$carrito a
     * partir de los OrdenMesaItem actuales de la mesa (igual que
     * CarritoVenta::cargarCarritoDesdeOrdenMesa()), para poder facturar la
     * mesa sin depender de que el cliente offline mande los precios.
     */
    public function cargarCarritoDesdeOrdenMesa(int $mesaId, int $empresaId): array
    {
        $orden = OrdenMesa::where('empresa_id', $empresaId)
            ->where('mesa_id', $mesaId)
            ->whereIn('estado', ['abierta', 'en_preparacion'])
            ->with('items.producto')
            ->latest()
            ->first();

        if (! $orden) {
            return [];
        }

        $carrito = [];

        foreach ($orden->items as $item) {
            $producto = $item->producto;
            if (! $producto) {
                continue;
            }

            $key = (string) $item->id;
            $precio = (float) $item->precio_unitario;
            $cant = (float) $item->cantidad;

            $carrito[$key] = [
                'uuid' => $key,
                'id_producto' => $producto->id_producto,
                'nombre' => $producto->descripcion_larga,
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
