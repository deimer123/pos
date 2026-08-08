<?php

namespace App\Filament\Resources\TecnicoResource\Pages;

use App\Filament\Resources\TecnicoResource;
use Filament\Resources\Pages\ListRecords;

class ListTecnicos extends ListRecords
{
    protected static string $resource = TecnicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
