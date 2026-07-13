<?php

namespace App\Filament\Resources\CatalogoResource\Pages;

use App\Filament\Resources\CatalogoResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditCatalogo extends EditRecord
{
    protected static string $resource = CatalogoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['nombre'])) {
            $data['slug'] = Str::slug($data['nombre']) . '-' . $this->record->empresa_id;
        }

        return $data;
    }
}
