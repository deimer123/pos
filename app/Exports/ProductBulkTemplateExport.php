<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId, protected bool $conVariantes = false, protected bool $conLotes = false)
    {
    }

    public function sheets(): array
    {
        $hojaProductos = match (true) {
            $this->conVariantes => new ProductoRopaBulkTemplatePlantillaSheet(),
            $this->conLotes => new ProductoLoteBulkTemplatePlantillaSheet(),
            default => new ProductBulkTemplatePlantillaSheet(),
        };

        return [
            'Productos' => $hojaProductos,
            'Referencia' => new ProductBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
