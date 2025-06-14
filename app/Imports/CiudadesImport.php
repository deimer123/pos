<?php

namespace App\Imports;

use App\Models\Ciudad;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CiudadesImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Saltar encabezado

            Ciudad::updateOrCreate(
                ['codigo_dian' => trim($row[1])],
                [
                    'nombre' => trim($row[0]),
                    'departamento_id' => intval($row[2]), // se asume que viene el ID directo
                ]
            );
        }
    }
}
