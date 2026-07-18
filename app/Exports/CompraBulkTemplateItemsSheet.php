<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompraBulkTemplateItemsSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
{
    // Fila desde donde se esconde la lista de productos existentes, muy por
    // debajo del area donde se escriben los items. Es la fuente del
    // desplegable (Data Validation tipo lista) de la columna Producto --
    // NO alimenta el autocompletado nativo de Excel al escribir, porque ese
    // requiere celdas contiguas sin huecos en blanco en la misma columna.
    // Publica (no privada): CompraBulkImport::collection() la usa para
    // saber a partir de que fila debe dejar de leer datos reales.
    public const FILA_LISTA_OCULTA = 1000;

    public function __construct(protected array $productosExistentes = [])
    {
    }

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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $productos = array_values(array_unique(array_filter($this->productosExistentes)));

                if (empty($productos)) {
                    return;
                }

                $primeraFila = self::FILA_LISTA_OCULTA;
                $ultimaFila = $primeraFila + count($productos) - 1;

                $sheet->setCellValue('A' . ($primeraFila - 1), 'No borrar: lista de apoyo del desplegable (productos existentes de este proveedor)');
                $sheet->getStyle('A' . ($primeraFila - 1))->getFont()->setItalic(true)->setColor(new Color('FF9CA3AF'));

                foreach ($productos as $i => $nombre) {
                    $sheet->setCellValue('A' . ($primeraFila + $i), $nombre);
                }

                $sheet->getStyle('A' . $primeraFila . ':A' . $ultimaFila)->getFont()->setColor(new Color('FFD1D5DB'));

                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(false);
                $validation->setShowDropDown(true);
                $validation->setPromptTitle('Producto de este proveedor');
                $validation->setPrompt('Dale clic a la flechita de la celda para ver los productos que ya existen. Si escribes uno que no aparece en la lista, se crea nuevo.');
                $validation->setFormula1('$A$' . $primeraFila . ':$A$' . $ultimaFila);

                for ($fila = 2; $fila <= $primeraFila - 2; $fila++) {
                    $sheet->getCell('A' . $fila)->setDataValidation(clone $validation);
                }
            },
        ];
    }
}
