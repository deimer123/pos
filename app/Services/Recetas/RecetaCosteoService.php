<?php

namespace App\Services\Recetas;

use App\Models\Product;
use App\Models\Receta;

/**
 * Formula de costeo de recetas compartida entre el formulario de Filament
 * (RecetaResource, que la aplica sobre valores del formulario en edicion) y
 * el recalculo automatico al confirmar una compra (Compra::confirmar()),
 * que la aplica sobre los items ya guardados en base de datos.
 */
class RecetaCosteoService
{
    /**
     * Costo con IVA de un producto (ingrediente o producto vendido). Prefiere
     * la columna precomputada costo_iva y, si no hay, calcula
     * precio_costo + IVA de iva_venta.
     */
    public static function costoConIvaProducto(?Product $producto): float
    {
        if (! $producto) {
            return 0.0;
        }

        $costoIva = (float) ($producto->costo_iva ?? 0);
        if ($costoIva > 0) {
            return round($costoIva, 2);
        }

        $costo = (float) ($producto->precio_costo ?? 0);
        $iva = (float) ($producto->iva_venta ?? 0);

        return round($costo + ($costo * $iva / 100), 2);
    }

    /**
     * Cantidad de un ingrediente convertida a la unidad "grande" en la que
     * normalmente se fija su costo (kg, litro) -- no existe una tabla de
     * conversion en el sistema, asi que se asume que gramo/mililitro son
     * siempre la fraccion /1000 de kilogramo/litro.
     */
    public static function cantidadEnUnidadDeCosto(float $cantidad, string $unidad): float
    {
        return match ($unidad) {
            'gr', 'ml' => $cantidad / 1000,
            default => $cantidad,
        };
    }

    /**
     * Costo por porcion de una receta, segun los items ya guardados en
     * base de datos (y el costo actual de cada ingrediente).
     */
    public static function costoPorPorcion(Receta $receta): float
    {
        $receta->loadMissing('items.ingrediente');

        $costoTotal = 0.0;

        foreach ($receta->items as $item) {
            $costoUnitario = static::costoConIvaProducto($item->ingrediente);
            $cantidadBase = static::cantidadEnUnidadDeCosto((float) $item->cantidad, (string) $item->unidad);
            $cantidadConMerma = $cantidadBase * (1 + ((float) $item->merma) / 100);

            $costoTotal += $costoUnitario * $cantidadConMerma;
        }

        $rendimiento = (float) $receta->rendimiento ?: 1.0;

        return round($costoTotal / $rendimiento, 2);
    }

    /**
     * Recalcula el costo por porcion de la receta y lo escribe en el
     * producto final que vende esa receta (mismos campos que actualiza el
     * boton manual "Actualizar costo del producto" en RecetaResource).
     */
    public static function actualizarCostoProducto(Receta $receta): ?float
    {
        $producto = $receta->producto ?? ($receta->product_id ? Product::find($receta->product_id) : null);

        if (! $producto) {
            return null;
        }

        $nuevoCosto = static::costoPorPorcion($receta);

        $datos = [
            'precio_costo' => $nuevoCosto,
            'precio_con_descuento' => $nuevoCosto,
            'costo_iva' => $nuevoCosto,
        ];

        $precioVenta = (float) $producto->precio_venta1;
        if ($precioVenta > 0) {
            $datos['utilidad1'] = round((($precioVenta - $nuevoCosto) / $precioVenta) * 100, 2);
        }

        $producto->update($datos);

        return $nuevoCosto;
    }

    /**
     * Recalcula (y aplica al producto final) el costo de todas las recetas
     * activas de la empresa que usan el producto dado como ingrediente.
     * Se llama al confirmar una compra, justo despues de actualizar el
     * costo del producto comprado, para que el costo se propague solo sin
     * depender del boton manual.
     */
    public static function actualizarRecetasQueUsanIngrediente(int $ingredienteProductId, int $empresaId): void
    {
        $recetas = Receta::where('empresa_id', $empresaId)
            ->whereHas('items', fn ($q) => $q->where('ingrediente_product_id', $ingredienteProductId))
            ->with('items.ingrediente', 'producto')
            ->get();

        foreach ($recetas as $receta) {
            static::actualizarCostoProducto($receta);
        }
    }
}
