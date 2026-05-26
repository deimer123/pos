<?php

namespace App\Filament\Resources\VentasPorVendedorReportResource\Widgets;

use App\Filament\Resources\VentasPorVendedorReportResource\Pages\ListVentasPorVendedorReports;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VentasPorVendedorStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListVentasPorVendedorReports::class;
    }

    protected function getStats(): array
    {
        $rows = $this->getPageTableQuery()->get();

        $responsables = $rows->count();
        $facturas = (int) $rows->sum('total_facturas');
        $ventas = (float) $rows->sum('total_ventas');
        $saldo = (float) $rows->sum('total_saldo');
        $promedioFactura = $facturas > 0 ? $ventas / $facturas : 0;

        return [
            Stat::make('Responsables con ventas', number_format($responsables, 0, ',', '.'))
                ->description('Segun filtros')
                ->icon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('Facturas', number_format($facturas, 0, ',', '.'))
                ->description('Segun filtros')
                ->icon('heroicon-o-document-text')
                ->color('gray'),

            Stat::make('Ventas filtradas', $this->money($ventas))
                ->description('Total vendido')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Promedio factura', $this->money($promedioFactura))
                ->description('Ventas / facturas')
                ->icon('heroicon-o-chart-bar')
                ->color('info'),
        ];
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
