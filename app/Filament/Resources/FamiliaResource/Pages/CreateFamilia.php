<?php

namespace App\Filament\Resources\FamiliaResource\Pages;

use App\Filament\Resources\FamiliaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFamilia extends CreateRecord
{
    protected static string $resource = FamiliaResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
