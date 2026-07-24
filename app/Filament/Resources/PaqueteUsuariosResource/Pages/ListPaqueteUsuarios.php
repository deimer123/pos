<?php

namespace App\Filament\Resources\PaqueteUsuariosResource\Pages;

use App\Filament\Resources\PaqueteUsuariosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaqueteUsuarios extends ListRecords
{
    protected static string $resource = PaqueteUsuariosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
