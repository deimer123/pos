<?php

namespace App\Imports;

use App\Models\Familia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FamiliaImport implements ToCollection, WithHeadingRow
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Familia::updateOrCreate(
                [
                    'id' => $row['idfamilia1'],
                    'empresa_id' => $this->empresaId,
                ],
                [
                    'nombre' => $row['nfamilia1'],
                    'empresa_id' => $this->empresaId,
                ]
            );
        }
    }
}
