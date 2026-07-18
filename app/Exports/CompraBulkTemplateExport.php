<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CompraBulkTemplateExport implements WithMultipleSheets
{
    public function __construct(protected int $empresaId)
    {
    }

    public function sheets(): array
    {
        return [
            'Items' => new CompraBulkTemplateItemsSheet(),
            'Referencia' => new CompraBulkTemplateReferenciaSheet($this->empresaId),
        ];
    }
}
