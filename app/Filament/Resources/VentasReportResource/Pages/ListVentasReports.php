<?php

namespace App\Filament\Resources\VentasReportResource\Pages;

use App\Filament\Resources\VentasReportResource;
use App\Filament\Resources\VentasReportResource\Widgets\VentasReportStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListVentasReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = VentasReportResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Ventas';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Ventas';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VentasReportStats::class,
        ];
    }
}
