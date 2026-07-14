<?php

namespace App\Filament\Resources\ReporteDescuentosResource\Pages;

use App\Filament\Resources\ReporteDescuentosResource;
use App\Filament\Resources\ReporteDescuentosResource\Widgets\ReporteDescuentosStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListReporteDescuentos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ReporteDescuentosResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Descuentos';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Descuentos';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReporteDescuentosStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 1;
    }
}
