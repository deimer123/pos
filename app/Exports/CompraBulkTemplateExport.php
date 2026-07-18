<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CompraBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId, protected array $productosExistentes = [])
    {
    }

    public function sheets(): array
    {
        return [
            'Items' => new CompraBulkTemplateItemsSheet($this->productosExistentes),
            'Referencia' => new CompraBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
