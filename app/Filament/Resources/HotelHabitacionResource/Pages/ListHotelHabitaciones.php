<?php

namespace App\Filament\Resources\HotelHabitacionResource\Pages;

use App\Filament\Resources\HotelHabitacionResource;
use Filament\Resources\Pages\ListRecords;

class ListHotelHabitaciones extends ListRecords
{
    protected static string $resource = HotelHabitacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
