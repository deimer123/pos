<?php

namespace App\Filament\Pages;

use App\Services\Grupo\CambioDeEmpresa;
use Filament\Pages\Page;

// Lista de sucursales del dueno, con el boton "Entrar". Solo la ve la
// empresa matriz (admin_empresa con sucursales ligadas), y no mientras esta
// dentro de una sucursal.
class MisSucursales extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = '👨‍💼 Administración';
    protected static ?string $navigationLabel = 'Mis sucursales';
    protected static ?string $title = 'Mis sucursales';
    protected static ?string $slug = 'mis-sucursales';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.mis-sucursales';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->esEmpresa()
            && $user->hasRole('admin_empresa')
            && ! app(CambioDeEmpresa::class)->estaEnSucursal()
            && $user->sucursales()->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getViewData(): array
    {
        return ['sucursales' => app(CambioDeEmpresa::class)->filasParaDueno(auth()->user())];
    }
}
