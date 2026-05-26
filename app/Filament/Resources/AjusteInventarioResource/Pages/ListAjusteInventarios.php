<?php

namespace App\Filament\Resources\AjusteInventarioResource\Pages;

use App\Filament\Resources\AjusteInventarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAjusteInventarios extends ListRecords
{
    protected static string $resource = AjusteInventarioResource::class;

   protected function getHeaderActions(): array
{
    return [
        \Filament\Actions\Action::make('crear')
            ->label('Crear ajuste inventario')
            ->url('/inventario') // 🔥 AQUÍ
            ->icon('heroicon-o-plus')
    ];
}
}
