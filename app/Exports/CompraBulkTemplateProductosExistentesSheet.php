<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Hoja de consulta: muestra los datos ACTUALES de cada producto que ya
// existe (del proveedor elegido), para que el usuario sepa que costo/IVA/
// precio/etc tiene ahora mismo un producto antes de decidir que escribir
// en la hoja Items. Es solo informativa, no la lee el importador.
class CompraBulkTemplateProductosExistentesSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    private const UNIDADES = [
        1 => 'Pieza',
        2 => 'Kilogramos',
        3 => 'Litros',
        4 => 'Metros',
        5 => 'Horas',
    ];

    public function __construct(protected Collection $productos)
    {
    }

    public function title(): string
    {
        return 'Productos existentes';
    }

    public function headings(): array
    {
        return [
            'Producto',
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
        return $this->productos->map(fn ($p) => [
            $p->descripcion_larga,
            (float) $p->precio_costo,
            (float) $p->descuento_comercial,
            (float) $p->iva_compra,
            (float) $p->utilidad1,
            (float) $p->precio_venta1,
            optional($p->familia1)->nombre ?? '-',
            optional($p->subfamilia)->nombre ?? '-',
            self::UNIDADES[(int) $p->id_unidad_de_medida] ?? '-',
        ])->toArray();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 15,
            'C' => 18,
            'D' => 10,
            'E' => 12,
            'F' => 15,
            'G' => 20,
            'H' => 20,
            'I' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A1:I1')->applyFromArray([
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

        $highestRow = max($sheet->getHighestRow(), 2);

        $sheet->getStyle('A1:I' . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('B2:F' . $highestRow)->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2');

        // Autofiltro (sin envolverlo en una Tabla, para no arriesgar que
        // Excel le aplique su propio tema de color y pise el encabezado
        // verde de arriba). Con esto solo, el desplegable de cada columna
        // ya trae la cajita de busqueda nativa de Excel que filtra al
        // escribir -- funciona igual, sin necesidad de una Tabla completa.
        $sheet->setAutoFilter('A1:I' . $highestRow);

        return [];
    }
}
