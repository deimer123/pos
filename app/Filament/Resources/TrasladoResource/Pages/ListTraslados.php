<?php

namespace App\Filament\Resources\TrasladoResource\Pages;

use App\Filament\Resources\TrasladoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTraslados extends ListRecords
{
    protected static string $resource = TrasladoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nuevo traslado')];
    }
}
