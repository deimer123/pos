<?php

namespace App\Filament\Resources\ReporteMecanicosResource\Pages;

use App\Filament\Resources\ReporteMecanicosResource;
use App\Filament\Resources\ReporteMecanicosResource\Widgets\ReporteMecanicosStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListReporteMecanicos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ReporteMecanicosResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Servicios por Mecánico';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte por Mecánico';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReporteMecanicosStats::class,
        ];
    }
}
