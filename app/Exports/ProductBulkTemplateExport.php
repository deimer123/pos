<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId)
    {
    }

    public function sheets(): array
    {
        return [
            'Productos' => new ProductBulkTemplatePlantillaSheet(),
            'Referencia' => new ProductBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
