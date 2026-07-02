<?php

namespace App\Filament\Resources\MecanicoResource\Pages;

use App\Filament\Resources\MecanicoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMecanico extends CreateRecord
{
    protected static string $resource = MecanicoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = auth()->user()->getEmpresaActualId();
        return $data;
    }
}
