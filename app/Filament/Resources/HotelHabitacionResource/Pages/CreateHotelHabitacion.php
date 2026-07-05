<?php

namespace App\Filament\Resources\HotelHabitacionResource\Pages;

use App\Filament\Resources\HotelHabitacionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHotelHabitacion extends CreateRecord
{
    protected static string $resource = HotelHabitacionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        $data['precios_por_persona'] = $this->extraerPreciosPorPersona($data);

        return $data;
    }

    private function extraerPreciosPorPersona(array &$data): array
    {
        $precios = [];

        for ($n = 1; $n <= HotelHabitacionResource::MAX_PERSONAS; $n++) {
            $key = "precio_{$n}";
            if (isset($data[$key]) && $data[$key] !== '') {
                $precios[(string) $n] = (float) str_replace('.', '', (string) $data[$key]);
            }
            unset($data[$key]);
        }

        return $precios;
    }
}
