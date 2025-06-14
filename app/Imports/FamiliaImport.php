<?php

namespace App\Imports;

use App\Models\Familia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FamiliaImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Familia::updateOrCreate(
                ['id' => $row['idfamilia1']],
                ['nombre' => $row['nfamilia1']]
            );
        }
    }
}
