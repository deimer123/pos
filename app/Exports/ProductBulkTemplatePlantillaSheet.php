<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Productos';
    }

    public function headings(): array
    {
        return [
            'Nombre del producto',
            'Proveedor',
            'Departamento',
            'Subfamilia',
            'Unidad de Medida',
            'Precio Costo',
            'Descuento Comercial',
            'IVA Compra',
            'IVA Venta',
            'Precio de Venta',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Ejemplo: Aceite 20W50 x Galon',
                'Distribuidora ABC',
                'Lubricantes',
                'Aceites',
                'Pieza',
                25000,
                0,
                19,
                19,
                35000,
            ],
        ];
    }
}
