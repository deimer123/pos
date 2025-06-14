<?php

namespace App\Imports;

use App\Models\Departamento;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class DepartamentosImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Saltar encabezado

            $nombre = trim($row[0]);
            $codigo = trim($row[1]);

            if ($nombre && $codigo) {
                Departamento::updateOrCreate(
                    ['codigo_dian' => $codigo],
                    ['nombre' => $nombre]
                );
            }
        }
    }
}
