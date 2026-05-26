<?php

namespace App\Filament\Resources\CarteraResource\Pages;

use App\Filament\Resources\CarteraResource;
use App\Filament\Resources\CarteraResource\Widgets\CarteraStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCarteras extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CarteraResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CarteraStats::class,
        ];
    }
}
