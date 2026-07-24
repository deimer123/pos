<?php

namespace App\Filament\Resources\ComplementoResource\Pages;

use App\Filament\Resources\ComplementoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComplementos extends ListRecords
{
    protected static string $resource = ComplementoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
