<?php

namespace App\Filament\Resources\VentasPorVendedorReportResource\Pages;

use App\Filament\Resources\VentasPorVendedorReportResource;
use App\Filament\Resources\VentasPorVendedorReportResource\Widgets\VentasPorVendedorStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListVentasPorVendedorReports extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = VentasPorVendedorReportResource::class;

    public function getTitle(): string
    {
        return 'Reporte de Ventas por Vendedor';
    }

    public function getBreadcrumb(): string
    {
        return 'Reporte de Ventas por Vendedor';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VentasPorVendedorStats::class,
        ];
    }
}
