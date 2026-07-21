<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
{
    private const ULTIMA_FILA_FORMULAS = 500;

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
            'Utilidad',
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
                15,
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
            'J' => 12,
            'K' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:K1')->applyFromArray([
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

        $sheet->getStyle('A2:K2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A1:K20')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('F2:K20')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Misma precaucion que CompraBulkTemplateItemsSheet: se pide
                // la hoja "Productos" por nombre, explicitamente, para no
                // arriesgarse a que la formula quede escrita en otra hoja
                // del mismo archivo (este export tiene tambien "Referencia").
                $spreadsheet = $event->sheet->getDelegate()->getParent();
                $sheet = $spreadsheet->getSheetByName('Productos');

                if (! $sheet) {
                    return;
                }

                $ultimaFila = self::ULTIMA_FILA_FORMULAS;

                // Precio de Venta (K) se calcula solo a partir de Precio
                // Costo (F), Descuento Comercial (G), IVA Venta (I) y
                // Utilidad (J) -- misma formula que usa
                // ProductBulkImport::collection() como respaldo. Empieza en
                // la fila 3 para dejar la fila 2 (el ejemplo ya lleno) como
                // ilustracion estatica, igual que en la plantilla de compras.
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    $sheet->setCellValue(
                        'K' . $fila,
                        "=IF(\$A{$fila}=\"\",\"\",IF(OR(F{$fila}=\"\",J{$fila}=\"\"),\"\",MROUND(F{$fila}*(1-G{$fila}/100)*(1+I{$fila}/100)/(1-J{$fila}/100),100)))"
                    );
                }
            },
        ];
    }
}
