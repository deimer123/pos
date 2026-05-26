<?php

namespace App\Filament\Widgets;

use App\Models\Factura;
use App\Models\Gasto;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class StatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') !== true;
    }

    protected function getCards(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $ventasHoy = Factura::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', today())
            ->sum('total');

        $ventasContadoHoy = Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'contado')
            ->whereDate('fecha', today())
            ->sum('total');

        $ventasCreditoHoy = Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'credito')
            ->whereDate('fecha', today())
            ->sum('total');

        $ventasMes = Factura::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $ventasContadoMes = Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'contado')
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $ventasCreditoMes = Factura::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_pago', 'credito')
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $gastosMes = Gasto::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'salida')
            ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('monto');

        return [
            Card::make('Ventas del dia', '$ ' . number_format((float) $ventasHoy, 0, ',', '.'))
                ->description(
                    'Contado $ ' . number_format((float) $ventasContadoHoy, 0, ',', '.') .
                    ' | Credito $ ' . number_format((float) $ventasCreditoHoy, 0, ',', '.')
                )
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Card::make('Ventas del mes', '$ ' . number_format((float) $ventasMes, 0, ',', '.'))
                ->description(
                    'Contado $ ' . number_format((float) $ventasContadoMes, 0, ',', '.') .
                    ' | Credito $ ' . number_format((float) $ventasCreditoMes, 0, ',', '.')
                )
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Card::make('Gastos del mes', '$ ' . number_format((float) $gastosMes, 0, ',', '.'))
                ->description('Salidas de caja')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),
        ];
    }
}
