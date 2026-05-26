<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
