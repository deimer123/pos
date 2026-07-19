<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CompraBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId, protected Collection $productosExistentes)
    {
    }

    public function sheets(): array
    {
        return [
            'Items' => new CompraBulkTemplateItemsSheet($this->productosExistentes->pluck('descripcion_larga')->toArray()),
            'Productos existentes' => new CompraBulkTemplateProductosExistentesSheet($this->productosExistentes),
            'Referencia' => new CompraBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
