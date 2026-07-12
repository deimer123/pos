<?php

namespace App\Filament\Resources\CierresCajaReportResource\Widgets;

use App\Filament\Resources\CierresCajaReportResource\Pages\ListCierresCajaReports;
use App\Support\CajaReportMetrics;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class CierresCajaStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListCierresCajaReports::class;
    }

    protected function getStats(): array
    {
        $desde = data_get($this->tableFilters, 'fecha.desde');
        $hasta = data_get($this->tableFilters, 'fecha.hasta');
        $totals = CajaReportMetrics::totalsForCajasPeriod(
            $this->getPageTableQuery()->get(),
            $desde ? Carbon::parse($desde)->startOfDay() : null,
            $hasta ? Carbon::parse($hasta)->endOfDay() : null,
        );

        $mostrarDetalle = (bool) data_get($this->tableFilters, 'mostrar_detalle_cards.activo', false);

        $stats = [
            Stat::make('Total vendido', $this->money($totals['ventas']))
                ->description('Contado y credito')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Efectivo', $this->money($totals['efectivo']))
                ->description('Ventas, cartera y movimientos')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Transferencia', $this->money($totals['transferencia']))
                ->description('Ventas, cartera y movimientos')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),

            Stat::make('Credito', $this->money($totals['ventas_credito']))
                ->description('Ventas a credito')
                ->icon('heroicon-o-credit-card')
                ->color('warning'),
        ];

        if ($mostrarDetalle) {
            $stats = array_merge($stats, [
                Stat::make('Cartera', $this->money($totals['cartera']))
                    ->description('Efectivo ' . $this->money($totals['cartera_efectivo']) . ' | Transf. ' . $this->money($totals['cartera_transferencia']))
                    ->icon('heroicon-o-receipt-refund')
                    ->color('primary'),

                Stat::make('Costo de venta', $this->money($totals['costo']))
                    ->description('Costo con IVA incluido')
                    ->icon('heroicon-o-calculator')
                    ->color('warning'),

                Stat::make('Utilidad', $this->money($totals['utilidad']))
                    ->description('Venta - costo')
                    ->icon('heroicon-o-chart-bar')
                    ->color($totals['utilidad'] < 0 ? 'danger' : 'success'),

                Stat::make('Utilidad %', number_format((float) $totals['utilidad_pct'], 2, ',', '.') . ' %')
                    ->description('Utilidad / venta')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->color($totals['utilidad_pct'] < 0 ? 'danger' : 'info'),

                Stat::make('Movimientos caja', $this->money($totals['movimientos_neto']))
                    ->description('Entradas ' . $this->money($totals['movimientos_entrada']) . ' | Salidas ' . $this->money($totals['movimientos_salida']))
                    ->icon('heroicon-o-arrows-right-left')
                    ->color($totals['movimientos_neto'] < 0 ? 'danger' : 'success'),

                Stat::make('Diferencia cierre', $this->money($totals['diferencia_efectivo']))
                    ->description('Efectivo cierre ' . $this->money($totals['monto_cierre']) . ' | Transferencia ' . $this->money($totals['transferencia']))
                    ->icon('heroicon-o-scale')
                    ->color($totals['diferencia_efectivo'] < 0 ? 'danger' : 'success'),

                Stat::make('Devoluciones', $this->money($totals['devoluciones']))
                    ->description('Con pago ' . $this->money($totals['devoluciones_con_pago']) . ' | Sin pago ' . $this->money($totals['devoluciones_sin_pago']) . ' | Registros ' . number_format((int) $totals['devoluciones_count'], 0, ',', '.'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color($totals['devoluciones'] > 0 ? 'danger' : 'gray'),
            ]);
        }

        return array_map(
            fn (Stat $stat) => $stat->extraAttributes(['class' => 'cierres-caja-stat']),
            $stats
        );
    }

    protected function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}