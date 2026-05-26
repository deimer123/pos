<?php

namespace App\Filament\Resources\CarteraResource\Widgets;

use App\Filament\Resources\CarteraResource\Pages\ListCarteras;
use App\Models\Factura;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CarteraStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListCarteras::class;
    }

    protected function getStats(): array
    {
        $cartera = $this->getPageTableQuery();
        $ventasCredito = (float) (clone $cartera)->sum('total');
        $saldoPendiente = (float) (clone $cartera)->where('saldo', '>', 0)->sum('saldo');
        $saldoVencido = (float) (clone $cartera)
            ->where('saldo', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->sum('saldo');

        return [
            Stat::make('Ventas credito', $this->money($ventasCredito))
                ->description('Facturas a credito')
                ->icon('heroicon-o-credit-card')
                ->color('info'),

            Stat::make('Saldo pendiente', $this->money($saldoPendiente))
                ->description('Cartera por cobrar')
                ->icon('heroicon-o-clock')
                ->color($saldoPendiente > 0 ? 'warning' : 'success'),

            Stat::make('Saldo vencido', $this->money($saldoVencido))
                ->description('Fuera de fecha')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($saldoVencido > 0 ? 'danger' : 'success'),
        ];
    }

    private function money(float $value): string
    {
        return '$ ' . number_format($value, 0, ',', '.');
    }
}
