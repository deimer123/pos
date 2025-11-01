<?php

namespace App\Imports;

use App\Models\AlternateCode;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlternateCodesImport implements ToModel, WithHeadingRow
{
    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function model(array $row)
    {
        // Buscar el producto por su campo 'id_producto'
        $producto = Product::where('id_producto', $row['idproducto'])
            ->where('empresa_id', $this->empresaId)
            ->first();

        // Si no se encuentra, ignorar la fila
        if (!$producto) {
            
            return null;
        }

        return new AlternateCode([
            'product_id' => $producto->id, // el ID real (clave primaria)
            'code'       => $row['codigoalterno'],
            'empresa_id' => $this->empresaId,
        ]);
    }
}
