<?php

namespace App\Filament\Resources\TecnicoResource\Pages;

use App\Filament\Resources\TecnicoResource;
use App\Models\Mecanico;
use Filament\Resources\Pages\CreateRecord;

class CreateTecnico extends CreateRecord
{
    protected static string $resource = TecnicoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        // El default de la columna es "mecanico" (ver migracion
        // add_rol_a_mecanicos_table) -- sin esto, un tecnico creado aqui
        // quedaria mezclado con los mecanicos de taller.
        $data['rol'] = Mecanico::ROL_TECNICO;
        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
