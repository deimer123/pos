<?php

namespace App\Filament\Resources\ReporteServiciosResource\Widgets;

use App\Filament\Resources\ReporteServiciosResource\Pages\ListReporteServicios;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ReporteServiciosStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListReporteServicios::class;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $registros = $this->getPageTableQuery()->get();

        $totalFacturado = (float) $registros->sum('subtotal');
        $totalEmpresa   = (float) $registros->sum(fn ($r) => $r->valor_empresa);
        $totalTercero   = (float) $registros->sum(fn ($r) => $r->valor_tercero);

        // Cuáles líneas de servicio "propio" ya fueron pagadas al mecánico (liquidadas)
        $idsPropio = $registros->where('tipo_servicio', 'propio')->pluck('id');
        $idsLiquidados = $idsPropio->isEmpty()
            ? collect()
            : DB::table('liquidacion_mecanico_detalles')
                ->whereIn('factura_detalle_id', $idsPropio)
                ->pluck('factura_detalle_id')
                ->unique();

        $registrosPropio = $registros->where('tipo_servicio', 'propio');
        $montoLiquidado  = (float) $registrosPropio->whereIn('id', $idsLiquidados)->sum(fn ($r) => $r->valor_tercero);
        $montoPendiente  = (float) $registrosPropio->whereNotIn('id', $idsLiquidados)->sum(fn ($r) => $r->valor_tercero);

        $stats = [
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

            Stat::make('Ya liquidado a mecánicos', $this->money($montoLiquidado))
                ->description('Servicios propios ya pagados')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Falta por liquidar', $this->money($montoPendiente))
                ->description('Servicios propios pendientes de pago')
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];

        return array_map(
            fn (Stat $stat) => $stat->extraAttributes(['class' => 'reporte-servicios-stat']),
            $stats
        );
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
