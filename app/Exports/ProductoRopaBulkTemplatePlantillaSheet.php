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

// Plantilla de cargue masivo para empresas con tipo_negocio = ropa_calzado:
// cada fila es UNA variante (talla/color); varias filas con el mismo
// "Nombre del producto" crean un solo producto con varias variantes (ver
// App\Imports\ProductoRopaBulkImport). Costo/Descuento/IVA/Utilidad solo se
// llenan en la PRIMERA fila de cada producto -- las filas siguientes (otra
// talla/color del mismo producto) los dejan en blanco a proposito.
class ProductoRopaBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
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
            'Talla',
            'Color',
            'Cantidad',
            'Proveedor',
            'Departamento',
            'Subfamilia',
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
                'Ejemplo: Camisa Polo',
                'M',
                'Azul',
                5,
                'Distribuidora ABC',
                'Ropa',
                'Camisas',
                30000,
                0,
                19,
                19,
                30,
                51000,
            ],
            [
                'Ejemplo: Camisa Polo',
                'L',
                'Azul',
                3,
                '',
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
            'E' => 22,
            'F' => 18,
            'G' => 18,
            'H' => 13,
            'I' => 16,
            'J' => 12,
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

        $sheet->getStyle('D2:D20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('H2:K20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('M2:M20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('L2:L' . self::ULTIMA_FILA_FORMULAS)->getNumberFormat()->setFormatCode('#,##0.00');

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

                // Precio de Venta (M) se calcula solo a partir de Precio
                // Costo (H), Descuento Comercial (I), IVA Venta (K) y
                // Utilidad (L) -- misma formula que usa
                // ProductoRopaBulkImport::confirmar() como respaldo. Se deja
                // en blanco si falta el nombre del producto, el costo o la
                // utilidad (las filas de continuacion de un mismo producto,
                // con otra talla/color, dejan esas celdas vacias a proposito).
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    $sheet->setCellValue(
                        'M' . $fila,
                        "=IF(\$A{$fila}=\"\",\"\",IF(OR(H{$fila}=\"\",L{$fila}=\"\"),\"\",MROUND(H{$fila}*(1-I{$fila}/100)*(1+K{$fila}/100)/(1-L{$fila}/100),100)))"
                    );
                }
            },
        ];
    }
}
