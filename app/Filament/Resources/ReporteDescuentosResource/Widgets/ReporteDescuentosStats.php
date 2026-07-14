<?php

namespace App\Filament\Resources\ReporteDescuentosResource\Widgets;

use App\Filament\Resources\ReporteDescuentosResource;
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
        return 5;
    }

    protected function getStats(): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $fechaFiltro = $this->tableFilters['fecha'] ?? [];
        $desde = $fechaFiltro['desde'] ?? null;
        $hasta = $fechaFiltro['hasta'] ?? null;

        $registrosConDescuento = $this->getPageTableQuery()->get();

        $registrosTodos = ReporteDescuentosResource::baseQueryTodos($empresaId)
            ->when($desde, fn ($q, $fecha) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '>=', $fecha)))
            ->when($hasta, fn ($q, $fecha) => $q->whereHas('factura', fn ($f) => $f->whereDate('fecha', '<=', $fecha)))
            ->get();

        $totalProductosConDescuento = $registrosConDescuento->count();
        $totalUnidadesTodas = (float) $registrosTodos->sum('cantidad_total');
        $totalUnidadesConDescuento = (float) $registrosConDescuento->sum('cantidad_total');
        $totalDescontado = (float) $registrosConDescuento->sum('valor_descontado');
        $totalVendidoTodo = (float) $registrosTodos->sum('valor_con_descuento');
        $totalVendidoConDescuento = (float) $registrosConDescuento->sum('valor_con_descuento');
        $totalVendidoSinDescuento = $totalVendidoTodo - $totalVendidoConDescuento;
        $descuentoPromedio = $totalProductosConDescuento > 0
            ? (float) $registrosConDescuento->avg(fn ($r) => abs((float) $r->descuento_promedio))
            : 0;

        $stats = [
            Stat::make('Productos vendidos', number_format($totalUnidadesTodas, 0, ',', '.'))
                ->description('Con o sin descuento')
                ->icon('heroicon-o-shopping-bag')
                ->color('gray'),

            Stat::make('Productos vendidos con descuento', number_format($totalUnidadesConDescuento, 0, ',', '.'))
                ->description($totalProductosConDescuento . ' productos distintos')
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Valor vendido sin descuento', $this->money($totalVendidoSinDescuento))
                ->description('A precio normal')
                ->icon('heroicon-o-banknotes')
                ->color('gray'),

            Stat::make('Total descontado', $this->money($totalDescontado))
                ->description('Lo que se dejó de cobrar')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger'),

            Stat::make('Total vendido con descuento', $this->money($totalVendidoConDescuento))
                ->description('% promedio: ' . number_format($descuentoPromedio, 2, ',', '.') . ' %')
                ->icon('heroicon-o-receipt-percent')
                ->color('success'),
        ];

        return array_map(
            fn (Stat $stat) => $stat->extraAttributes(['class' => 'reporte-descuentos-stat']),
            $stats
        );
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
