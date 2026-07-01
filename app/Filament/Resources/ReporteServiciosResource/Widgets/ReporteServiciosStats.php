<?php

namespace App\Filament\Resources\ReporteServiciosResource\Widgets;

use App\Filament\Resources\ReporteServiciosResource\Pages\ListReporteServicios;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReporteServiciosStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListReporteServicios::class;
    }

    protected function getStats(): array
    {
        $registros = $this->getPageTableQuery()->get();

        $totalFacturado = (float) $registros->sum('subtotal');
        $totalEmpresa   = (float) $registros->sum(fn ($r) => $r->valor_empresa);
        $totalTercero   = (float) $registros->sum(fn ($r) => $r->valor_tercero);

        return [
            Stat::make('Total facturado en servicios', $this->money($totalFacturado))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary'),

            Stat::make('Para la empresa', $this->money($totalEmpresa))
                ->description('Servicios propios (% configurado)')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Para terceros / mecánicos', $this->money($totalTercero))
                ->description('No es ganancia de la empresa')
                ->icon('heroicon-o-arrow-uturn-right')
                ->color('warning'),
        ];
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
