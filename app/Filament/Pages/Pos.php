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
        // 🔥 REDIRECCIÓN AUTOMÁTICA
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