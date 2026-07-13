<?php

namespace App\Filament\Resources\CatalogoResource\Pages;

use App\Filament\Resources\CatalogoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatalogos extends ListRecords
{
    protected static string $resource = CatalogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
