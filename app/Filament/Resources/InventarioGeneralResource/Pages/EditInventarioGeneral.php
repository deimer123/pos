<?php

namespace App\Filament\Resources\InventarioGeneralResource\Pages;

use App\Filament\Resources\InventarioGeneralResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventarioGeneral extends EditRecord
{
    protected static string $resource = InventarioGeneralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
