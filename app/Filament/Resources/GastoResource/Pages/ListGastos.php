<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use App\Filament\Resources\GastoResource\Widgets\GastosStats;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListGastos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GastosStats::class,
        ];
    }
}
