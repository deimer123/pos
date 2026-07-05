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
        $data['recargo_aire'] = $this->limpiarMonto($data['recargo_aire'] ?? null);
        $data['recargo_ventilador'] = $this->limpiarMonto($data['recargo_ventilador'] ?? null);

        return $data;
    }

    private function extraerPreciosPorPersona(array &$data): array
    {
        $precios = [];

        for ($n = 1; $n <= HotelHabitacionResource::MAX_PERSONAS; $n++) {
            $key = "precio_{$n}";
            if (isset($data[$key]) && $data[$key] !== '') {
                $precios[(string) $n] = $this->limpiarMonto($data[$key]);
            }
            unset($data[$key]);
        }

        return $precios;
    }

    private function limpiarMonto($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (float) str_replace('.', '', (string) $valor);
    }
}
