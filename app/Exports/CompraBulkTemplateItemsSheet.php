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

class CompraBulkTemplateItemsSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function title(): string
    {
        return 'Items';
    }

    public function headings(): array
    {
        return [
            'Producto',
            'Cantidad',
            'Costo Unitario',
            'Descuento Comercial',
            'IVA',
            'Utilidad',
            'Precio de Venta',
            'Departamento',
            'Subfamilia',
            'Unidad de Medida',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Ejemplo: Aceite 20W50 x Galon',
                10,
                25000,
                0,
                19,
                30,
                '',
                'Lubricantes',
                'Aceites',
                'Pieza',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 12,
            'C' => 15,
            'D' => 18,
            'E' => 10,
            'F' => 12,
            'G' => 15,
            'H' => 20,
            'I' => 20,
            'J' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '15803D'],
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

        $sheet->getStyle('B2:G20')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        return [];
    }
}
