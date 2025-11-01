<?php
namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductosYActoresImport implements WithMultipleSheets
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function sheets(): array
    {
        return [
            'productos' => new ProductImport($this->empresaId),
            'actores'   => new ActorImport($this->empresaId),
        ];
    }
}
