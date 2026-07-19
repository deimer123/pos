<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Pos extends Page
{
    protected static ?string $navigationLabel = 'POS';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = '⚙️ Operaciones';
        protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.pos';

    public function mount()
    {
        // admin_empresa puede tener varios destinos (POS normal, mesas,
        // domicilios, taller, hotel, cocina) segun los modulos activos de
        // su empresa -- se manda a la misma pantalla de eleccion que ya ve
        // al iniciar sesion, en vez de asumir "POS normal" directo.
        if (auth()->user()->hasRole('admin_empresa')) {
            return redirect()->route('eleccion');
        }

        // vendedor/cajero solo tienen un destino (el POS), igual que en /eleccion.
        return redirect('/pos');
    }

    public static function shouldRegisterNavigation(): bool
{
    return auth()->check() &&
        auth()->user()->hasAnyRole([
            'admin_empresa',
            'vendedor',
            'cajero'
        ]);
}
}