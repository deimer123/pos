<?php

namespace App\Imports;

use App\Models\AlternateCode;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlternateCodesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new AlternateCode([
            'product_id' => $row['idproducto'],     // Asegúrate de que coincida con el nombre de la columna
            'code'       => $row['codigoalterno'],  // en minúsculas y sin espacios
        ]);
    }
}

