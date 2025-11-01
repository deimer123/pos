<?php

namespace App\Filament\Resources\SubfamiliaResource\Pages;

use App\Filament\Resources\SubfamiliaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubfamilias extends ListRecords
{
    protected static string $resource = SubfamiliaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
