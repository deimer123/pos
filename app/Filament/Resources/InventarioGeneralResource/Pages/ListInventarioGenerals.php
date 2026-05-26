<?php

namespace App\Filament\Resources\InventarioGeneralResource\Pages;

use App\Filament\Resources\InventarioGeneralResource;
use Filament\Resources\Pages\ListRecords;

class ListInventarioGenerals extends ListRecords
{
    protected static string $resource = InventarioGeneralResource::class;

    public function getTitle(): string
    {
        return 'Inventario de Productos';
    }

    public function getBreadcrumb(): string
    {
        return 'Inventario de Productos';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\InventarioResumen::class,
        ];
    }
}
