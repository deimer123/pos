<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Pages\ImportarCompras;
use App\Filament\Resources\CompraResource;
use App\Models\Compra;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompras extends ListRecords
{
    protected static string $resource = CompraResource::class;

    /**
     * Acciones de encabezado (ejemplo: Crear compra)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cargaMasiva')
                ->label('Carga masiva')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => ImportarCompras::getUrl())
                ->visible(fn () => auth()->user()->hasRole('admin_empresa')),

            Actions\CreateAction::make()
                ->label('Crear compra'),
        ];
    }

    /**
     * Controla qué página abrir según el estado de la compra.
     */
    protected function getTableRecordUrlUsing(): ?\Closure
    {
        return function (Compra $record): ?string {
            return $record->estado === 'borrador'
                ? \App\Filament\Resources\CompraResource\Pages\EditCompra::getUrl(['record' => $record])
                : \App\Filament\Resources\CompraResource\Pages\ViewCompra::getUrl(['record' => $record]);
        };
    }
}
