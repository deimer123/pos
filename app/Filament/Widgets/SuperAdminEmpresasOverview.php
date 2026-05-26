<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class SuperAdminEmpresasOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $empresas = User::query()
            ->where('tipo_usuario', 'empresa')
            ->role('admin_empresa');

        $total = (clone $empresas)->count();
        $activas = (clone $empresas)
            ->where('activo', true)
            ->where(fn ($query) =>
                $query->whereNull('plan_ends_at')
                    ->orWhereDate('plan_ends_at', '>=', today())
            )
            ->count();
        $vencidas = (clone $empresas)
            ->whereDate('plan_ends_at', '<', today())
            ->count();
        $porVencer = (clone $empresas)
            ->where('activo', true)
            ->whereBetween('plan_ends_at', [today(), today()->addDays(15)])
            ->count();

        return [
            Card::make('Empresas registradas', number_format($total, 0, ',', '.'))
                ->description('Clientes creados en el sistema')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Card::make('Empresas activas', number_format($activas, 0, ',', '.'))
                ->description('Con plan vigente')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Card::make('Planes por vencer', number_format($porVencer, 0, ',', '.'))
                ->description('Vencen en los proximos 15 dias')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Card::make('Empresas vencidas', number_format($vencidas, 0, ',', '.'))
                ->description('Requieren reactivacion')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }
}
