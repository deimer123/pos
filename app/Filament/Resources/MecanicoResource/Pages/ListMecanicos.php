<?php

namespace App\Filament\Resources\MecanicoResource\Pages;

use App\Filament\Resources\MecanicoResource;
use Filament\Resources\Pages\ListRecords;

class ListMecanicos extends ListRecords
{
    protected static string $resource = MecanicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
