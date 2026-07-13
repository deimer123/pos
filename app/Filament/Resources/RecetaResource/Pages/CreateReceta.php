<?php

namespace App\Filament\Resources\RecetaResource\Pages;

use App\Filament\Resources\RecetaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceta extends CreateRecord
{
    protected static string $resource = RecetaResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
