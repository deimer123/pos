<?php

namespace App\Filament\Resources\ConfiguracionEmpresaResource\Pages;

use App\Filament\Resources\ConfiguracionEmpresaResource;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracionEmpresa extends EditRecord
{
    protected static string $resource = ConfiguracionEmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_merge($data, \App\Models\ConfiguracionEmpresa::flagsModuloParaTipo($data['tipo_negocio'] ?? 'tienda'));
    }
}
