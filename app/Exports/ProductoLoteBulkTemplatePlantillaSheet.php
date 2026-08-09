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

// Plantilla de cargue masivo para empresas con el flag usa_lotes activo
// (droguería, víveres o cualquier otro tipo de negocio que lo prenda): cada
// fila es UN lote; varias filas con el mismo "Nombre del producto" crean un
// solo producto con varios lotes (ver App\Imports\ProductoLoteBulkImport).
// Costo/Descuento/IVA/Utilidad solo se llenan en la PRIMERA fila de cada
// producto -- las filas siguientes (otro lote del mismo producto) los dejan
// en blanco a proposito. Mismo patron que
// ProductoRopaBulkTemplatePlantillaSheet para talla/color.
//
// Esta plantilla NO trae cantidad/stock: los lotes se crean siempre en 0 y
// el stock inicial se ingresa despues por la plantilla de Compras (que ya
// reconoce Lote/Fecha de vencimiento para sumarle al lote existente, ver
// App\Imports\CompraBulkImport).
class ProductoLoteBulkTemplatePlantillaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
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
            'Lote',
            'Fecha de vencimiento',
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
                'Ejemplo: Acetaminofén 500mg',
                'L240523',
                '2027-05-01',
                'Distribuidora ABC',
                'Droguería',
                'Analgésicos',
                3000,
                0,
                19,
                19,
                30,
                5100,
            ],
            [
                'Ejemplo: Acetaminofén 500mg',
                'L240890',
                '2027-11-15',
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
            'B' => 14,
            'C' => 18,
            'D' => 22,
            'E' => 18,
            'F' => 18,
            'G' => 13,
            'H' => 16,
            'I' => 12,
            'J' => 12,
            'K' => 12,
            'L' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:L1')->applyFromArray([
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

        $sheet->getStyle('A2:L3')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A1:L20')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('C2:C' . self::ULTIMA_FILA_FORMULAS)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('G2:J20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('L2:L20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('K2:K' . self::ULTIMA_FILA_FORMULAS)->getNumberFormat()->setFormatCode('#,##0.00');

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

                // Precio de Venta (L) se calcula solo a partir de Precio
                // Costo (G), Descuento Comercial (H), IVA Venta (J) y
                // Utilidad (K) -- misma formula que usa
                // ProductoLoteBulkImport::confirmar() como respaldo. Se deja
                // en blanco si falta el nombre del producto, el costo o la
                // utilidad (las filas de continuacion de un mismo producto,
                // con otro lote, dejan esas celdas vacias a proposito).
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    $sheet->setCellValue(
                        'L' . $fila,
                        "=IF(\$A{$fila}=\"\",\"\",IF(OR(G{$fila}=\"\",K{$fila}=\"\"),\"\",MROUND(G{$fila}*(1-H{$fila}/100)*(1+J{$fila}/100)/(1-K{$fila}/100),100)))"
                    );
                }
            },
        ];
    }
}
