<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class InventarioResumen extends BaseWidget
{
    protected function getCards(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $productos = Product::query()->where('empresa_id', $empresaId);

        $costoTotalExpression = '
            existencias *
            (
                (COALESCE(precio_costo, 0) * (1 - (COALESCE(descuento_comercial, 0) / 100))) *
                (1 + (COALESCE(iva_venta, 0) / 100))
            )
        ';

        $costo = (clone $productos)->sum(\DB::raw($costoTotalExpression));
        $venta = (clone $productos)->sum(\DB::raw('existencias * COALESCE(precio_venta1, 0)'));
        $utilidad = $venta - $costo;
        $utilidadPorcentaje = $venta > 0
            ? ($utilidad / $venta) * 100
            : 0;

        return [
            Card::make('Inventario Costo', '$ ' . number_format($costo, 0, ',', '.')),
            Card::make('Inventario Venta', '$ ' . number_format($venta, 0, ',', '.')),
            Card::make('Utilidad', '$ ' . number_format($utilidad, 0, ',', '.'))
                ->description(number_format($utilidadPorcentaje, 2, ',', '.') . ' % sobre la venta'),
        ];
    }
}
