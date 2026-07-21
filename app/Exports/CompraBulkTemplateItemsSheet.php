<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

    public function __construct(protected array $productosExistentes = [], protected bool $conVariantes = false)
    {
    }

    public function title(): string
    {
        return 'Items';
    }

    /**
     * Orden logico de columnas. Talla/Color van entre Producto y Cantidad
     * (para empresas con variantes) porque hay que saber la variante ANTES
     * de la cantidad, no al final -- el importador (CompraBulkImport) lee
     * por nombre de encabezado, no por posicion, asi que el orden aqui es
     * libre.
     */
    private function columnas(): array
    {
        $columnas = ['Producto'];

        if ($this->conVariantes) {
            $columnas[] = 'Talla';
            $columnas[] = 'Color';
        }

        return array_merge($columnas, [
            'Cantidad',
            'Costo Unitario',
            'Descuento Comercial',
            'IVA',
            'Utilidad',
            'Precio de Venta',
            'Departamento',
            'Subfamilia',
            'Unidad de Medida',
        ]);
    }

    private function letra(string $nombreColumna): string
    {
        $indice = array_search($nombreColumna, $this->columnas(), true);

        return Coordinate::stringFromColumnIndex($indice + 1);
    }

    public function headings(): array
    {
        return $this->columnas();
    }

    public function array(): array
    {
        $valores = [
            'Producto' => 'Ejemplo: Aceite 20W50 x Galon',
            'Talla' => '',
            'Color' => '',
            'Cantidad' => 10,
            'Costo Unitario' => 25000,
            'Descuento Comercial' => 0,
            'IVA' => 19,
            'Utilidad' => 30,
            'Precio de Venta' => '',
            'Departamento' => 'Lubricantes',
            'Subfamilia' => 'Aceites',
            'Unidad de Medida' => 'Pieza',
        ];

        return [array_map(fn ($columna) => $valores[$columna], $this->columnas())];
    }

    public function columnWidths(): array
    {
        $anchos = [
            'Producto' => 34,
            'Talla' => 10,
            'Color' => 14,
            'Cantidad' => 12,
            'Costo Unitario' => 15,
            'Descuento Comercial' => 18,
            'IVA' => 10,
            'Utilidad' => 12,
            'Precio de Venta' => 15,
            'Departamento' => 20,
            'Subfamilia' => 20,
            'Unidad de Medida' => 16,
        ];

        $resultado = [];

        foreach ($this->columnas() as $nombre) {
            $resultado[$this->letra($nombre)] = $anchos[$nombre];
        }

        return $resultado;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $ultimaColumna = $this->letra('Unidad de Medida');

        $sheet->getStyle("A1:{$ultimaColumna}1")->applyFromArray([
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

        $sheet->getStyle("A2:{$ultimaColumna}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A1:{$ultimaColumna}20")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle($this->letra('Cantidad') . '2:' . $this->letra('IVA') . '20')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($this->letra('Utilidad') . '2:' . $this->letra('Utilidad') . '500')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($this->letra('Precio de Venta') . '2:' . $this->letra('Precio de Venta') . '20')->getNumberFormat()->setFormatCode('#,##0');

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

                $colCosto = $this->letra('Costo Unitario');
                $colDescuento = $this->letra('Descuento Comercial');
                $colIva = $this->letra('IVA');
                $colUtilidad = $this->letra('Utilidad');
                $colPrecioVenta = $this->letra('Precio de Venta');

                // Rango real de 'Productos existentes' (encabezado + una
                // fila por producto), en vez de columnas enteras ($A:$I).
                // Un VLOOKUP/COINCIDIR sobre una columna entera (mas de un
                // millon de filas) es carisimo de EVALUAR de verdad -- Excel
                // lo tolera mostrando solo el resultado en pantalla, pero
                // CompraBulkImport (WithCalculatedFormulas) si necesita
                // calcularlo de verdad al importar, y con rango sin limite
                // se quedaba sin memoria. Acotado al tamaño real, es
                // practicamente instantaneo.
                $ultimaFilaExistentes = max(count($this->productosExistentes) + 1, 2);
                $rangoExistentesA = "'Productos existentes'!\$A\$1:\$A\${$ultimaFilaExistentes}";
                $rangoExistentesCompleto = "'Productos existentes'!\$A\$1:\$I\${$ultimaFilaExistentes}";

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
                            "OFFSET('Productos existentes'!\$A\$1,MATCH(A{$fila}&\"*\",{$rangoExistentesA},0)-1,0,COUNTIF({$rangoExistentesA},A{$fila}&\"*\"),1)"
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
                // Utilidad sigue SIN formula a proposito: es una decision de
                // precio de esta compra, no algo que deba copiarse ciego del
                // historico. Precio de Venta se calcula a partir de Costo con
                // Descuento Comercial aplicado primero, despues IVA, despues
                // Utilidad (no se copia el precio de venta anterior del
                // producto), redondeado a la centena, para que siempre quede
                // consistente con lo que el usuario haya puesto en
                // Costo/Descuento/IVA/Utilidad. Misma formula que usa
                // CompraBulkImport::validar() como respaldo (costoConDesc =
                // costo*(1-desc/100), costoConIva = costoConDesc*(1+iva/100),
                // pv = costoConIva/(1-utilidad/100)).
                for ($fila = 3; $fila <= $ultimaFila; $fila++) {
                    foreach ([
                        $colCosto => 2, // Costo Unitario
                        $colIva => 4, // IVA
                    ] as $columna => $indiceProductosExistentes) {
                        // Coincidencia EXACTA (sin comodines "*"): la
                        // libreria que calcula formulas al importar
                        // (WithCalculatedFormulas en CompraBulkImport) no
                        // soporta bien los comodines en VLOOKUP -- con
                        // comodin devolvia vacio o error en vez del dato
                        // real. Coincidencia exacta funciona igual de bien
                        // aca porque el desplegable de Producto (arriba)
                        // pega el nombre completo tal cual esta en
                        // 'Productos existentes', asi que siempre calza.
                        $sheet->setCellValue(
                            $columna . $fila,
                            "=IF(\$A{$fila}=\"\",\"\",IFERROR(VLOOKUP(\$A{$fila},{$rangoExistentesCompleto},{$indiceProductosExistentes},FALSE),\"\"))"
                        );
                    }

                    $sheet->setCellValue(
                        $colPrecioVenta . $fila,
                        "=IF(\$A{$fila}=\"\",\"\",IF({$colUtilidad}{$fila}=\"\",\"\",MROUND({$colCosto}{$fila}*(1-{$colDescuento}{$fila}/100)*(1+{$colIva}{$fila}/100)/(1-{$colUtilidad}{$fila}/100),100)))"
                    );
                }
            },
        ];
    }
}
