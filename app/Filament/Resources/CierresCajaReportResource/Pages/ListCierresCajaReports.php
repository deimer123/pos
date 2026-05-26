<?php

namespace App\Filament\Resources\CierresCajaReportResource\Pages;

use App\Filament\Resources\CierresCajaReportResource;
use App\Filament\Resources\CierresCajaReportResource\Widgets\CierresCajaStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCierresCajaReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CierresCajaReportResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Cierres de Caja';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Cierres de Caja';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CierresCajaStats::class,
        ];
    }
}
