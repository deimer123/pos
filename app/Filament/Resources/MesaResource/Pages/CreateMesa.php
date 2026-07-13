<?php

namespace App\Filament\Resources\MesaResource\Pages;

use App\Filament\Resources\MesaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMesa extends CreateRecord
{
    protected static string $resource = MesaResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
