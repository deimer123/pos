<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductoVariante;

/**
 * Cuando una variante se crea/edita/borra desde el Repeater de
 * ProductResource (alta de stock inicial, correccion manual, etc.),
 * products.existencias debe quedar igual a la suma del stock de sus
 * variantes activas -- si no, el POS/catalogo siguen mostrando el
 * stock viejo del producto aunque las variantes ya tengan existencias.
 * Las ventas/compras/ajustes/devoluciones NO pasan por aqui: esas ya
 * mueven producto y variante en paralelo por su propia cuenta (ver
 * FacturarVentaService, Compra::confirmar, AplicarAjusteInventarioService,
 * CarritoVenta devoluciones), asi que recalcular la suma completa aqui no
 * les pisa nada.
 */
class ProductoVarianteObserver
{
    public function saved(ProductoVariante $variante): void
    {
        $this->recalcular($variante);
    }

    public function deleted(ProductoVariante $variante): void
    {
        $this->recalcular($variante);
    }

    private function recalcular(ProductoVariante $variante): void
    {
        $producto = Product::find($variante->product_id);
        if (! $producto) {
            return;
        }

        $totalVariantes = ProductoVariante::where('product_id', $producto->id)
            ->where('activo', true)
            ->sum('stock');

        if ((float) $producto->existencias !== (float) $totalVariantes) {
            $producto->update(['existencias' => $totalVariantes]);
        }
    }
}
