<?php

namespace App\Filament\Resources\DevolucionesReportResource\Pages;

use App\Filament\Resources\DevolucionesReportResource;
use App\Filament\Resources\DevolucionesReportResource\Widgets\DevolucionesReportStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListDevolucionesReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = DevolucionesReportResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Devoluciones';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Devoluciones';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DevolucionesReportStats::class,
        ];
    }
}