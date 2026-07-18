<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Kardex extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationGroup = 'Inventario';
    protected static string $view = 'filament.pages.kardex';
    protected static ?int $navigationSort = 2;

    public function mount()
    {
        return redirect('/kardex');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->hasRole('admin_empresa') || ($user->hasRole('digitador') && $user->puedeVerResource('kardex')));
    }
}
