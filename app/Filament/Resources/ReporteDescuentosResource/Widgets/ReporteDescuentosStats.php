<?php

namespace App\Filament\Resources\ReporteDescuentosResource\Widgets;

use App\Filament\Resources\ReporteDescuentosResource\Pages\ListReporteDescuentos;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReporteDescuentosStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListReporteDescuentos::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $registros = $this->getPageTableQuery()->get();

        $totalProductos = $registros->count();
        $totalUnidades = (float) $registros->sum('cantidad_total');
        $totalDescontado = (float) $registros->sum('valor_descontado');
        $totalConDescuento = (float) $registros->sum('valor_con_descuento');
        $descuentoPromedio = $totalProductos > 0
            ? (float) $registros->avg(fn ($r) => abs((float) $r->descuento_promedio))
            : 0;

        return [
            Stat::make('Productos con descuento', number_format($totalProductos, 0, ',', '.'))
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Unidades vendidas con descuento', number_format($totalUnidades, 0, ',', '.'))
                ->icon('heroicon-o-cube')
                ->color('gray'),

            Stat::make('Total descontado', $this->money($totalDescontado))
                ->description('Lo que se dejó de cobrar')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger'),

            Stat::make('Total vendido con descuento', $this->money($totalConDescuento))
                ->description('% promedio: ' . number_format($descuentoPromedio, 2, ',', '.') . ' %')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
