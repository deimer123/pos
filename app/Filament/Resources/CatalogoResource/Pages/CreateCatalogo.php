<?php

namespace App\Filament\Resources\CatalogoResource\Pages;

use App\Filament\Resources\CatalogoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCatalogo extends CreateRecord
{
    protected static string $resource = CatalogoResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['nombre'])) {
            $data['slug'] = Str::slug($data['nombre']) . '-' . $data['empresa_id'];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
