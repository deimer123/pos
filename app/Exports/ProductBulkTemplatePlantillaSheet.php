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

class ProductBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
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

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 24,
            'C' => 20,
            'D' => 20,
            'E' => 16,
            'F' => 14,
            'G' => 18,
            'H' => 13,
            'I' => 13,
            'J' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:J1')->applyFromArray([
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

        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A1:J20')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('F2:J20')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        return [];
    }
}
