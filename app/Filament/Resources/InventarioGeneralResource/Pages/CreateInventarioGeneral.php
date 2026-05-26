<?php

namespace App\Filament\Resources\InventarioGeneralResource\Pages;

use App\Filament\Resources\InventarioGeneralResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInventarioGeneral extends CreateRecord
{
    protected static string $resource = InventarioGeneralResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
