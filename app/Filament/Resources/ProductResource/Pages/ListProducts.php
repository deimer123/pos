<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\ImportarProductos;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;



class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cargaMasiva')
                ->label('Carga masiva')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => ImportarProductos::getUrl())
                ->visible(fn () => auth()->user()->hasRole('admin_empresa')),

            Actions\CreateAction::make()->label('Crear Productos'),
        ];
    }

    

    
}
