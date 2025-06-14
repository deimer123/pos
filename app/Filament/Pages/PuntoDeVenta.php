<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PuntoDeVenta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Punto de Venta';
    protected static string $view = 'filament.pages.punto-de-venta';
}
