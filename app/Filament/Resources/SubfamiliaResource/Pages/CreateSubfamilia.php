<?php

namespace App\Filament\Resources\SubfamiliaResource\Pages;

use App\Filament\Resources\SubfamiliaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSubfamilia extends CreateRecord
{
    protected static string $resource = SubfamiliaResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
