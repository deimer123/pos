<?php

namespace App\Filament\Resources\HotelHabitacionResource\Pages;

use App\Filament\Resources\HotelHabitacionResource;
use Filament\Resources\Pages\EditRecord;

class EditHotelHabitacion extends EditRecord
{
    protected static string $resource = HotelHabitacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $precios = $data['precios_por_persona'] ?? [];

        for ($n = 1; $n <= HotelHabitacionResource::MAX_PERSONAS; $n++) {
            $data["precio_{$n}"] = $precios[(string) $n] ?? null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $precios = [];

        for ($n = 1; $n <= HotelHabitacionResource::MAX_PERSONAS; $n++) {
            $key = "precio_{$n}";
            if (isset($data[$key]) && $data[$key] !== '') {
                $precios[(string) $n] = (float) $data[$key];
            }
            unset($data[$key]);
        }

        $data['precios_por_persona'] = $precios;

        return $data;
    }
}
