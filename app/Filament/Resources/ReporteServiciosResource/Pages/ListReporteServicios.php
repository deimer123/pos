<?php

namespace App\Filament\Resources\ReporteServiciosResource\Pages;

use App\Filament\Resources\ReporteServiciosResource;
use App\Filament\Resources\ReporteServiciosResource\Widgets\ReporteServiciosStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListReporteServicios extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ReporteServiciosResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Servicios';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Servicios';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReporteServiciosStats::class,
        ];
    }
}
