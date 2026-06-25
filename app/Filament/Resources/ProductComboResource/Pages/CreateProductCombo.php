<?php

namespace App\Filament\Resources\ProductComboResource\Pages;

use App\Filament\Resources\ProductComboResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCombo extends CreateRecord
{
    protected static string $resource = ProductComboResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
