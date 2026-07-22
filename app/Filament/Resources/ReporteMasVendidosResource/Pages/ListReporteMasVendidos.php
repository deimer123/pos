<?php

namespace App\Filament\Resources\ReporteMasVendidosResource\Pages;

use App\Filament\Resources\ReporteMasVendidosResource;
use Filament\Resources\Pages\ListRecords;

class ListReporteMasVendidos extends ListRecords
{
    protected static string $resource = ReporteMasVendidosResource::class;

    public function getTitle(): string
    {
        return 'Más vendidos';
    }

    public function getBreadcrumb(): string
    {
        return 'Más vendidos';
    }
}
