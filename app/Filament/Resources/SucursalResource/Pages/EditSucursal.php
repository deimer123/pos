<?php

namespace App\Filament\Resources\SucursalResource\Pages;

use App\Filament\Resources\SucursalResource;
use Filament\Resources\Pages\EditRecord;

class EditSucursal extends EditRecord
{
    protected static string $resource = SucursalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // La Principal siempre queda activa y nunca cambia de empresa.
        if ($this->record->es_principal) {
            $data['activo'] = true;
        }

        unset($data['empresa_id'], $data['es_principal']);

        return $data;
    }
}
