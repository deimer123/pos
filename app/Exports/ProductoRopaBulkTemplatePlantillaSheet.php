<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Plantilla de cargue masivo para empresas con tipo_negocio = ropa_calzado:
// cada fila es UNA variante (talla/color); varias filas con el mismo
// "Nombre del producto" crean un solo producto con varias variantes (ver
// App\Imports\ProductoRopaBulkImport).
class ProductoRopaBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function title(): string
    {
        return 'Productos';
    }

    public function headings(): array
    {
        return [
            'Nombre del producto',
            'Talla',
            'Color',
            'Cantidad',
            'Precio Extra',
            'Proveedor',
            'Departamento',
            'Subfamilia',
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
                'Ejemplo: Camisa Polo',
                'M',
                'Azul',
                5,
                0,
                'Distribuidora ABC',
                'Ropa',
                'Camisas',
                30000,
                0,
                19,
                19,
                50000,
            ],
            [
                'Ejemplo: Camisa Polo',
                'L',
                'Azul',
                3,
                0,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 10,
            'C' => 14,
            'D' => 12,
            'E' => 13,
            'F' => 22,
            'G' => 18,
            'H' => 18,
            'I' => 13,
            'J' => 16,
            'K' => 12,
            'L' => 12,
            'M' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4338CA'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getStyle('A2:M3')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A1:M20')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('D2:E20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('I2:M20')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        return [];
    }
}
