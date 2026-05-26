<?php

namespace App\Filament\Resources\NotaCreditoResource\Pages;

use App\Filament\Resources\NotaCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNotaCredito extends CreateRecord
{
    protected static string $resource = NotaCreditoResource::class;

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
