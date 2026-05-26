<?php

namespace App\Filament\Resources\ActorResource\Pages;

use App\Filament\Resources\ActorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateActor extends CreateRecord
{
    protected static string $resource = ActorResource::class;

    public function getTitle(): string
{
    $clasificacion = $this->data['clasificacion'] ?? null;

    return match ($clasificacion) {
        'cliente' => 'Crear Cliente',
        'proveedor' => 'Crear Proveedor',
        'cliente_proveedor' => 'Crear Cliente - Proveedor',
        default => 'Crear ',
    };
}

    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
