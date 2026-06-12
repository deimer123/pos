<?php

namespace App\Filament\Resources\CuentaContableResource\Pages;

use App\Filament\Resources\CuentaContableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCuentaContables extends ListRecords
{
    protected static string $resource = CuentaContableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear cuenta contable'),
        ];
    }
}
