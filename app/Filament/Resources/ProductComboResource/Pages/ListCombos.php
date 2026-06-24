<?php

namespace App\Filament\Resources\ProductComboResource\Pages;

use App\Filament\Resources\ProductComboResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCombos extends ListRecords
{
    protected static string $resource = ProductComboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
