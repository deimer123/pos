<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CompraBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId, protected Collection $productosExistentes, protected bool $conVariantes = false, protected bool $conLotes = false)
    {
    }

    public function sheets(): array
    {
        return [
            'Items' => new CompraBulkTemplateItemsSheet($this->productosExistentes->pluck('descripcion_larga')->toArray(), $this->conVariantes, $this->conLotes),
            'Productos existentes' => new CompraBulkTemplateProductosExistentesSheet($this->productosExistentes, $this->conVariantes, $this->conLotes),
            'Referencia' => new CompraBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
