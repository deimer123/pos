<?php

namespace App\Filament\Resources\ProveedorResource\Pages;

use App\Filament\Resources\ProveedorResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;

class CreateProveedor extends CreateRecord
{
    protected static string $resource = ProveedorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['clasificacion'] = 'proveedor';
        return $data;
    }
}
