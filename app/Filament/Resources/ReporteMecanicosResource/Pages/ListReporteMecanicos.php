<?php

namespace App\Filament\Resources\ReporteMecanicosResource\Pages;

use App\Filament\Resources\ReporteMecanicosResource;
use Filament\Resources\Pages\ListRecords;

class ListReporteMecanicos extends ListRecords
{
    protected static string $resource = ReporteMecanicosResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Servicios por Mecánico';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte por Mecánico';
    }
}
