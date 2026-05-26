<?php

namespace App\Filament\Resources\AjusteInventarioResource\Pages;

use App\Filament\Resources\AjusteInventarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAjusteInventario extends EditRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
