<?php

namespace App\Filament\Resources\ReporteTecnicosResource\Pages;

use App\Filament\Resources\ReporteTecnicosResource;
use App\Filament\Resources\ReporteTecnicosResource\Widgets\ReporteTecnicosStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListReporteTecnicos extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = ReporteTecnicosResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Servicios por Técnico';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte por Técnico';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReporteTecnicosStats::class,
        ];
    }
}
