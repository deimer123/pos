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
}
