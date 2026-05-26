<?php

namespace App\Filament\Resources\GastoResource\Widgets;

use App\Filament\Resources\GastoResource\Pages\ListGastos;
use App\Models\FacturaPago;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class GastosStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListGastos::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        $ventasCobradas = $this->ventasCobradas();
        $entradas = (float) (clone $query)->where('tipo', 'entrada')->sum('monto');
        $salidas = (float) (clone $query)->where('tipo', 'salida')->sum('monto');
        $neto = $ventasCobradas + $entradas - $salidas;

        return [
            Stat::make('Ventas cobradas', $this->money($ventasCobradas))
                ->description('Ventas y abonos segun fechas')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Entradas manuales', $this->money($entradas))
                ->description('Registradas como movimiento')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),

            Stat::make('Total gastos / salidas', $this->money($salidas))
                ->description('Segun filtros')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('danger'),

            Stat::make('Neto de caja', $this->money($neto))
                ->description('Ventas + entradas - salidas')
                ->icon('heroicon-o-scale')
                ->color($neto < 0 ? 'warning' : 'info'),
        ];
    }

    protected function ventasCobradas(): float
    {
        $empresaId = auth()->user()->getEmpresaActualId();
        $desde = data_get($this, 'tableFilters.fecha.fecha_desde');
        $hasta = data_get($this, 'tableFilters.fecha.fecha_hasta');

        return (float) FacturaPago::query()
            ->whereHas('factura', fn ($query) => $query->where('empresa_id', $empresaId))
            ->when($desde, fn ($query, $date) => $query->where('fecha', '>=', Carbon::parse($date)->startOfDay()))
            ->when($hasta, fn ($query, $date) => $query->where('fecha', '<=', Carbon::parse($date)->endOfDay()))
            ->sum('monto');
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
