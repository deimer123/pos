<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        if (auth()->user()?->hasRole('super_admin')) {
            return [
                \App\Filament\Widgets\SuperAdminEmpresasOverview::class,
            ];
        }

        return [
            \App\Filament\Widgets\StatsOverview::class,            
            \App\Filament\Widgets\ProductosMasVendidos::class,
            \App\Filament\Widgets\VentasPorVendedor::class,
            \App\Filament\Widgets\VentasUltimos7Dias::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (auth()->user()->hasRole('super_admin')) {
            return true;
        }

        // OCULTAR PARA DIGITADOR PURO
        if (
            auth()->user()->hasRole('digitador') &&
            !auth()->user()->hasAnyRole(['vendedor', 'cajero'])
        ) {
            return false;
        }

        return true;
    }

    
}
