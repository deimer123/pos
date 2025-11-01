<?php

namespace App\Imports;

use App\Models\Subfamilia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubfamiliaImport implements ToCollection, WithHeadingRow
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Subfamilia::updateOrCreate(
                [
                    'id_familia2' => $row['idfamilia2'],
                    'empresa_id'  => $this->empresaId,
                ],
                [
                    'id_familia1' => $row['idfamilia1'],
                    'nombre'      => $row['nfamilia2'],
                    'empresa_id'  => $this->empresaId,
                ]
            );
        }
    }
}
