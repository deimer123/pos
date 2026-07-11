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
        $data = array_merge($data, \App\Models\ConfiguracionEmpresa::flagsModuloParaTipo($data['tipo_negocio'] ?? 'tienda'));

        if (empty($data['slug']) && ! empty($data['nombre_empresa'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['nombre_empresa']) . '-' . $this->record->empresa_id;
        }

        return $data;
    }
}
