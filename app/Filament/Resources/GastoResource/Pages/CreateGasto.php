<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Models\Gasto;

use App\Filament\Resources\GastoResource;

use Filament\Resources\Pages\CreateRecord;

class CreateGasto extends CreateRecord
{
    protected static string $resource = GastoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $empresaId = auth()->user()->getEmpresaActualId();

        $ultimo = Gasto::where('empresa_id', $empresaId)
            ->max('id_gasto');

        $data['id_gasto'] = $ultimo ? $ultimo + 1 : 1;

        $data['empresa_id'] = $empresaId;
        $data['created_by'] = auth()->id();

        return $data;
    }

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
