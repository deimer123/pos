<?php

namespace App\Filament\Resources\SucursalResource\Pages;

use App\Filament\Resources\SucursalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSucursales extends ListRecords
{
    protected static string $resource = SucursalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
