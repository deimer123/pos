<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId, protected bool $conVariantes = false)
    {
    }

    public function sheets(): array
    {
        return [
            'Productos' => $this->conVariantes
                ? new ProductoRopaBulkTemplatePlantillaSheet()
                : new ProductBulkTemplatePlantillaSheet(),
            'Referencia' => new ProductBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
