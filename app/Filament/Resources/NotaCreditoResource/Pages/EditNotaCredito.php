<?php

namespace App\Filament\Resources\NotaCreditoResource\Pages;

use App\Filament\Resources\NotaCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNotaCredito extends EditRecord
{
    protected static string $resource = NotaCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
