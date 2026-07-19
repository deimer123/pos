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

        $sheet->getStyle('B2:E20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F2:F500')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('G2:G20')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // OJO: no confiar en $event->sheet->getDelegate() a secas.
                // Este archivo tiene 3 hojas (Items, Productos existentes,
                // Referencia); cuando mas de una implementa WithEvents, el
                // "sheet actual" que llega en el evento a veces no es esta
                // (se vio en la practica: las formulas de aqui terminaron
                // escritas en otra hoja del mismo archivo). Se pide la hoja
                // "Items" por nombre, explicitamente, al Spreadsheet padre.
                $spreadsheet = $event->sheet->getDelegate()->getParent();
                $sheet = $spreadsheet->getSheetByName('Items');

                if (! $sheet) {
                    return;
                }

                $ultimaFila = self::ULTIMA_FILA_FORMULAS;

                // Desplegable de la columna Producto: en vez de una lista
                // fija, la fuente es una formula OFFSET+COINCIDIR+CONTAR.SI
                // que filtra 'Productos existentes'!A por lo que ya llevas
                // escrito en la propia celda (comodin "texto*") -- asi el
                // desplegable de verdad se acorta mientras escribes.
                if (! empty(array_filter($this->productosExistentes))) {
                    for ($fila = 2; $fila <= $ultimaFila; $fila++) {
                        $validation = new DataValidation();
                        $validation->setType(DataValidation::TYPE_LIST);
                        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                        $validation->setAllowBlank(true);
                        $validation->setShowInputMessage(true);
                        $validation->setShowErrorMessage(false);
                        $validation->setShowDropDown(true);
                        $validation->setPromptTitle('Producto de este proveedor');
                        $validation->setPrompt('Escribe parte del nombre: el desplegable se filtra solo. Si no aparece, se crea un producto nuevo con ese nombre.');
                        $validation->setFormula1(
                            "OFFSET('Productos existentes'!\$A\$1,MATCH(A{$fila}&\"*\",'Productos existentes'!\$A:\$A,0)-1,0,COUNTIF('Productos existentes'!\$A:\$A,A{$fila}&\"*\"),1)"
                        );

                        $sheet->getCell('A' . $fila)->setDataValidation($validation);
                    }
                }

                // Autollenar Costo Unitario e IVA con los datos ACTUALES del
                // producto escrito/elegido en la columna A, buscandolo por
                // coincidencia parcial (comodin) en "Productos existentes".
                // Si el producto es nuevo (no aparece alla), estas celdas
                // quedan vacias y hay que llenarlas a mano. El usuario puede
                // sobreescribir cualquier celda si esta compra trae un dato
                // distinto al de siempre (ej: subio el costo).
                //
                // Descuento, Departamento, Subfamilia y Unidad de Medida ya
                // NO llevan formula (se dejo solo en Costo/IVA/Precio de
                // Venta): esas 4 solo importan para productos nuevos, que de
                // todas formas hay que escribir a mano, y tener formula ahi
                // no aportaba nada -- solo daba pie a que Excel se comportara
                // raro al escribir encima y cambiar de celda.
                //
                // Utilidad (F) sigue SIN formula a proposito: es una
                // decision de precio de esta compra, no algo que deba
                // copiarse ciego del historico. Precio de Venta (G) se
                // calcula solo a partir de Costo+IVA+Utilidad (no se copia
                // el precio de venta anterior del producto), redondeado a
                // la centena, para que siempre quede consistente con lo que
                // el usuario haya puesto en C/E/F.
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    foreach ([
                        'C' => 2, // Costo Unitario
                        'E' => 4, // IVA
                    ] as $columna => $indiceProductosExistentes) {
                        $sheet->setCellValue(
                            $columna . $fila,
                            "=IF(\$A{$fila}=\"\",\"\",IFERROR(VLOOKUP(\"*\"&\$A{$fila}&\"*\",'Productos existentes'!\$A:\$I,{$indiceProductosExistentes},FALSE),\"\"))"
                        );
                    }

                    $sheet->setCellValue(
                        'G' . $fila,
                        "=IF(\$A{$fila}=\"\",\"\",IF(F{$fila}=\"\",\"\",MROUND(C{$fila}*(1+E{$fila}/100)/(1-F{$fila}/100),100)))"
                    );
                }
            },
        ];
    }
}
