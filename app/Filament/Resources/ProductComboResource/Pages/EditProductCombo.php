<?php

namespace App\Filament\Resources\ProductComboResource\Pages;

use App\Filament\Resources\ProductComboResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCombo extends EditRecord
{
    protected static string $resource = ProductComboResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
