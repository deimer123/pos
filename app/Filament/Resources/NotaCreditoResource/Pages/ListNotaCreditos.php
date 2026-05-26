<?php

namespace App\Filament\Resources\NotaCreditoResource\Pages;

use App\Filament\Resources\NotaCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNotaCreditos extends ListRecords
{
    protected static string $resource = NotaCreditoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
