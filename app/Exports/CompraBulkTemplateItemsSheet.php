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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Ultima fila (inclusive) hasta donde se ponen el desplegable y las
// formulas de autollenado de esta hoja.
class CompraBulkTemplateItemsSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
{
    private const ULTIMA_FILA_FORMULAS = 500;

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
                $ultimaFila = self::ULTIMA_FILA_FORMULAS;

                // Desplegable de la columna Producto: apunta directo a la
                // columna A de la hoja "Productos existentes" (sin copiar
                // nada aqui). Data Validation si puede referenciar otra
                // hoja sin problema, a diferencia del autocompletado nativo.
                $cantidadProductos = count(array_filter($this->productosExistentes));

                if ($cantidadProductos > 0) {
                    $ultimaFilaLista = $cantidadProductos + 1; // +1 por el encabezado de esa hoja

                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(false);
                    $validation->setShowDropDown(true);
                    $validation->setPromptTitle('Producto de este proveedor');
                    $validation->setPrompt('Dale clic a la flechita de la celda para ver los productos que ya existen. Si escribes uno que no aparece en la lista, se crea nuevo.');
                    $validation->setFormula1("'Productos existentes'!\$A\$2:\$A\${$ultimaFilaLista}");

                    for ($fila = 2; $fila <= $ultimaFila; $fila++) {
                        $sheet->getCell('A' . $fila)->setDataValidation(clone $validation);
                    }
                }

                // Autollenar el resto de la fila con los datos ACTUALES del
                // producto escrito/elegido en la columna A, buscandolo en
                // la hoja "Productos existentes". Si el producto es nuevo
                // (no aparece alla), estas celdas quedan vacias y hay que
                // llenarlas a mano. El usuario puede sobreescribir cualquier
                // celda si esta compra trae un dato distinto al de siempre.
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    foreach ([
                        'C' => 2, // Costo Unitario
                        'D' => 3, // Descuento Comercial
                        'E' => 4, // IVA
                        'F' => 5, // Utilidad
                        'G' => 6, // Precio de Venta
                        'H' => 7, // Departamento
                        'I' => 8, // Subfamilia
                        'J' => 9, // Unidad de Medida
                    ] as $columna => $indiceProductosExistentes) {
                        $sheet->setCellValue(
                            $columna . $fila,
                            "=IFERROR(VLOOKUP(\$A{$fila},'Productos existentes'!\$A:\$I,{$indiceProductosExistentes},FALSE),\"\")"
                        );
                    }
                }
            },
        ];
    }
}
