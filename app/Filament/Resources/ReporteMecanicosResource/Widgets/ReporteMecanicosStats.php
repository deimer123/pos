<?php

namespace App\Filament\Resources\ReporteMecanicosResource\Widgets;

use App\Filament\Resources\ReporteMecanicosResource\Pages\ListReporteMecanicos;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ReporteMecanicosStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListReporteMecanicos::class;
    }

    protected function getStats(): array
    {
        $registros = $this->getPageTableQuery()->get();

        $totalCobrado    = (float) $registros->sum('subtotal');
        $montoMecanico   = (float) $registros->sum(fn ($r) => $r->valor_tercero);
        $gananciaEmpresa = (float) $registros->sum(fn ($r) => $r->valor_empresa);

        $facturaIds = $registros->pluck('factura_id')->unique();

        $totalRepuestos = $facturaIds->isEmpty() ? 0.0 : (float) DB::table('factura_detalles as fd')
            ->join('facturas as f', 'f.id', '=', 'fd.factura_id')
            ->join('products as p', function ($join) {
                $join->on('p.id_producto', '=', 'fd.producto_id')
                    ->on('p.empresa_id', '=', 'f.empresa_id');
            })
            ->whereIn('fd.factura_id', $facturaIds)
            ->where('p.tipo_producto', '!=', 'servicio')
            ->where('fd.producto_id', '!=', '10001')
            ->sum('fd.subtotal');

        return [
            Stat::make('Total cobrado en servicios', $this->money($totalCobrado))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary'),

            Stat::make('Le ha quedado a él', $this->money($montoMecanico))
                ->description('Su parte de los servicios')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Ganancia generada para la empresa', $this->money($gananciaEmpresa))
                ->description('% de la empresa en sus servicios')
                ->icon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Total repuestos en sus órdenes', $this->money($totalRepuestos))
                ->description('Productos facturados junto a sus servicios')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('warning'),
        ];
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
