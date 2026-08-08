<?php

namespace App\Services\Inventario;

use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\ProductoVariante;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un AjusteInventario ya confirmado a products.existencias y, cuando
 * la linea trae producto_variante_id o producto_lote_id, tambien a
 * producto_variantes.stock / producto_lotes.stock en paralelo (mismo
 * criterio de "stock en paralelo" de FacturarVentaService).
 *
 * Las lineas CON variante/lote se procesan aparte de las lineas SIN
 * variante/lote: el "cantidad_nueva"/"diferencia" de una linea con
 * variante/lote esta referido al stock DE ESA VARIANTE/LOTE, no al stock
 * total del producto -- si se mezclaran en el mismo calculo (como hacia el
 * codigo original, pensado para una sola linea por producto) el resultado
 * quedaria mal en cuanto un producto tiene mas de una variante/lote en el
 * mismo ajuste. El producto padre se recalcula como rollup: se mueve
 * exactamente lo mismo que se movieron, en conjunto, sus variantes/lotes
 * tocados en este ajuste.
 */
class AplicarAjusteInventarioService
{
    public function aplicar(AjusteInventario $ajuste): void
    {
        DB::transaction(function () use ($ajuste) {
            if ($ajuste->tipo === 'inventario_nuevo') {
                Product::where('empresa_id', $ajuste->empresa_id)->update(['existencias' => 0]);
                ProductoVariante::where('empresa_id', $ajuste->empresa_id)->update(['stock' => 0]);
                ProductoLote::where('empresa_id', $ajuste->empresa_id)->update(['stock' => 0]);
            }

            $todosLosDetalles = AjusteInventarioDetalle::where('ajuste_inventario_id', $ajuste->id)
                ->whereNotNull('producto_id')
                ->get();

            $this->aplicarSinVariante($ajuste, $todosLosDetalles->whereNull('producto_variante_id')->whereNull('producto_lote_id')->groupBy('producto_id'));
            $this->aplicarConVariante($ajuste, $todosLosDetalles->whereNotNull('producto_variante_id')->groupBy('producto_id'));
            $this->aplicarConLote($ajuste, $todosLosDetalles->whereNotNull('producto_lote_id')->groupBy('producto_id'));
        });
    }

    private function aplicarSinVariante(AjusteInventario $ajuste, $detallesPorProducto): void
    {
        foreach ($detallesPorProducto as $idProducto => $detalles) {
            $producto = Product::where('empresa_id', $ajuste->empresa_id)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            if (! $producto) {
                continue;
            }

            $existenciasAnteriores = (int) $producto->existencias;
            $diferenciaTotal = $detalles->sum(fn ($d) => $d->cantidad_nueva - $existenciasAnteriores);

            $existenciasNuevas = $ajuste->tipo === 'ajuste'
                ? $existenciasAnteriores + $diferenciaTotal
                : $detalles->last()->cantidad_nueva;

            if ($existenciasNuevas < 0) {
                throw new \Exception("Inventario negativo no permitido para el producto {$idProducto}");
            }

            $producto->update(['existencias' => $existenciasNuevas]);

            foreach ($detalles as $detalle) {
                $detalle->update([
                    'cantidad_anterior' => $existenciasAnteriores,
                    'diferencia' => $detalle->cantidad_nueva - $existenciasAnteriores,
                ]);
            }
        }
    }

    private function aplicarConVariante(AjusteInventario $ajuste, $detallesPorProducto): void
    {
        foreach ($detallesPorProducto as $idProducto => $detallesProducto) {
            $producto = Product::where('empresa_id', $ajuste->empresa_id)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            if (! $producto) {
                continue;
            }

            $deltaProducto = 0.0;

            foreach ($detallesProducto->groupBy('producto_variante_id') as $varianteId => $filas) {
                $variante = ProductoVariante::where('id', $varianteId)
                    ->where('empresa_id', $ajuste->empresa_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variante) {
                    continue;
                }

                $stockAnteriorVariante = (float) $variante->stock;
                $diferenciaTotal = $filas->sum(fn ($d) => $d->cantidad_nueva - $stockAnteriorVariante);

                $stockNuevoVariante = $ajuste->tipo === 'ajuste'
                    ? $stockAnteriorVariante + $diferenciaTotal
                    : (float) $filas->last()->cantidad_nueva;

                if ($stockNuevoVariante < 0) {
                    throw new \Exception("Inventario negativo no permitido para la variante \"{$variante->nombre}\"");
                }

                $variante->update(['stock' => $stockNuevoVariante]);
                $deltaProducto += ($stockNuevoVariante - $stockAnteriorVariante);

                foreach ($filas as $fila) {
                    $fila->update([
                        'cantidad_anterior' => $stockAnteriorVariante,
                        'diferencia' => $stockNuevoVariante - $stockAnteriorVariante,
                    ]);
                }
            }

            $producto->update(['existencias' => max(0, (float) $producto->existencias + $deltaProducto)]);
        }
    }

    private function aplicarConLote(AjusteInventario $ajuste, $detallesPorProducto): void
    {
        foreach ($detallesPorProducto as $idProducto => $detallesProducto) {
            $producto = Product::where('empresa_id', $ajuste->empresa_id)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            if (! $producto) {
                continue;
            }

            $deltaProducto = 0.0;

            foreach ($detallesProducto->groupBy('producto_lote_id') as $loteId => $filas) {
                $lote = ProductoLote::where('id', $loteId)
                    ->where('empresa_id', $ajuste->empresa_id)
                    ->lockForUpdate()
                    ->first();

                if (! $lote) {
                    continue;
                }

                $stockAnteriorLote = (float) $lote->stock;
                $diferenciaTotal = $filas->sum(fn ($d) => $d->cantidad_nueva - $stockAnteriorLote);

                $stockNuevoLote = $ajuste->tipo === 'ajuste'
                    ? $stockAnteriorLote + $diferenciaTotal
                    : (float) $filas->last()->cantidad_nueva;

                if ($stockNuevoLote < 0) {
                    throw new \Exception("Inventario negativo no permitido para el lote \"{$lote->lote}\"");
                }

                $lote->update(['stock' => $stockNuevoLote]);
                $deltaProducto += ($stockNuevoLote - $stockAnteriorLote);

                foreach ($filas as $fila) {
                    $fila->update([
                        'cantidad_anterior' => $stockAnteriorLote,
                        'diferencia' => $stockNuevoLote - $stockAnteriorLote,
                    ]);
                }
            }

            $producto->update(['existencias' => max(0, (float) $producto->existencias + $deltaProducto)]);
        }
    }
}
