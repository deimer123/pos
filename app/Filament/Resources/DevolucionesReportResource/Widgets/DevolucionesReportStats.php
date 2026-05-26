<?php

namespace App\Filament\Resources\DevolucionesReportResource\Widgets;

use App\Filament\Resources\DevolucionesReportResource\Pages\ListDevolucionesReports;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DevolucionesReportStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getTablePage(): string
    {
        return ListDevolucionesReports::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        $devolucionIds = (clone $query)->pluck('id');
        $total = (float) (clone $query)->sum('total');
        $count = (clone $query)->count();
        $items = $devolucionIds->isEmpty()
            ? 0
            : (float) DB::table('devolucion_detalles')
                ->whereIn('devolucion_id', $devolucionIds)
                ->sum('cantidad');

        return [
            Stat::make('Devoluciones', number_format($count, 0, ',', '.'))
                ->description('Registros filtrados')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger'),

            Stat::make('Total devuelto', $this->money($total))
                ->description('Valor de devoluciones')
                ->icon('heroicon-o-banknotes')
                ->color($total > 0 ? 'danger' : 'success'),

            Stat::make('Productos devueltos', $this->qty($items))
                ->description('Cantidad total')
                ->icon('heroicon-o-cube')
                ->color('info'),
        ];
    }

    private function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}