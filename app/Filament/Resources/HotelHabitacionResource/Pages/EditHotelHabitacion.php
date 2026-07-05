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
            $valor = $precios[(string) $n] ?? null;
            $data["precio_{$n}"] = $valor === null ? null : number_format((float) $valor, 0, ',', '.');
        }

        $data['recargo_aire'] = number_format((float) ($data['recargo_aire'] ?? 0), 0, ',', '.');
        $data['recargo_ventilador'] = number_format((float) ($data['recargo_ventilador'] ?? 0), 0, ',', '.');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $precios = [];

        for ($n = 1; $n <= HotelHabitacionResource::MAX_PERSONAS; $n++) {
            $key = "precio_{$n}";
            if (isset($data[$key]) && $data[$key] !== '') {
                $precios[(string) $n] = $this->limpiarMonto($data[$key]);
            }
            unset($data[$key]);
        }

        $data['precios_por_persona'] = $precios;
        $data['recargo_aire'] = $this->limpiarMonto($data['recargo_aire'] ?? null);
        $data['recargo_ventilador'] = $this->limpiarMonto($data['recargo_ventilador'] ?? null);

        return $data;
    }

    private function limpiarMonto($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (float) str_replace('.', '', (string) $valor);
    }
}
