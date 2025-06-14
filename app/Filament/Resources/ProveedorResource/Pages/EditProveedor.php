<?php

namespace App\Filament\Resources\ProveedorResource\Pages;

use App\Filament\Resources\ProveedorResource;
use Filament\Resources\Pages\EditRecord;

class EditProveedor extends EditRecord
{
    protected static string $resource = ProveedorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['clasificacion'] = 'proveedor';
        return $data;
    }
}
