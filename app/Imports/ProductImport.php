<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{

    protected $empresaId;

    public function __construct($empresaId)
    {
        $this->empresaId = $empresaId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            logger([
    'raw_id_proveedor' => $row['idproveedor'] ?? null,
    'casted' => (int) ($row['idproveedor'] ?? 0),
]);



            // Convertimos las claves a minúsculas por seguridad
            $row = collect($row)->mapWithKeys(fn($value, $key) => [strtolower(trim($key)) => $value]);

            // Solo importamos si tiene ID del producto y descripción
            if (empty($row['idproducto']) || empty($row['descripcionlarga'])) {
                continue;
            }

            // Limpiar valores con punto decimal y convertir
            $precioCosto   = str_replace(',', '.', $row['preciocosto'] ?? 0);
            $precioVenta1  = str_replace(',', '.', $row['precioventa1'] ?? 0);
            $utilidad1     = str_replace(',', '.', $row['utilidad1'] ?? 0);

            Product::updateOrCreate(
                [
                    'empresa_id'  => $this->empresaId,
                    'id_producto' => (int) $row['idproducto'],
                ],
                [
                    'id_familia1'         => $row['idfamilia1'] ?? 1,
                    'id_familia2'         => $row['idfamilia2'] ?? 1,
                    'descripcion_larga'   => $row['descripcionlarga'],
                    'id_proveedor'        => $row['idproveedor'] ?? null,
                    'iva_compra'          => $row['ivacompra'] ?? 0,
                    'iva_venta'           => $row['ivaventa'] ?? 0,
                    'existencias'         => $row['existencias'] ?? 0,
                    'precio_costo'        => $precioCosto,
                    'precio_venta1'       => $precioVenta1,
                    'utilidad1'           => $utilidad1,
                    'id_unidad_de_medida' => $row['id_unidaddemedida'] ?? 1,
                    'foto'                => $row['foto'] ?? null,
                ]
            );
        }
    }
}
