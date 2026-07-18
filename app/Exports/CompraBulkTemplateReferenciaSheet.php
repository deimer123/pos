<?php

namespace App\Exports;

use App\Models\Familia;
use App\Models\Subfamilia;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompraBulkTemplateReferenciaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles, WithEvents
{
    public function __construct(protected int $empresaId)
    {
    }

    public function title(): string
    {
        return 'Referencia';
    }

    public function headings(): array
    {
        return ['Instrucciones', 'Departamentos existentes', 'Subfamilias existentes'];
    }

    public function array(): array
    {
        $instrucciones = [
            'El Proveedor, N° de factura, Tipo de pago y Fecha se llenan una sola vez en la pantalla -- este excel es solo la lista de productos de esa factura.',
            'Producto, Cantidad y Costo Unitario son obligatorios. Las demas columnas se pueden dejar vacias.',
            'Producto: escribe el nombre. Si ya existe para el proveedor que elegiste, se actualiza su costo y sube su existencia. Si no existe, se crea de una.',
            'En la columna Producto (hoja Items), escribe parte del nombre: el desplegable de la celda se va filtrando solo con lo que llevas escrito. Si lo que buscas no aparece, es un producto nuevo.',
            'Al escribir/elegir un producto que ya existe, Costo Unitario, Descuento, IVA, Departamento, Subfamilia y Unidad de Medida se llenan solos con sus datos actuales. Puedes sobreescribir cualquiera si esta compra trae un dato distinto (ej: subio el costo).',
            'Utilidad la escribes tu (es una decision de precio de esta compra, no se copia del historico). Precio de Venta se calcula solo a partir de Costo + IVA + Utilidad, redondeado a la centena -- no hace falta escribirlo.',
            'Para buscar un producto por nombre en la hoja "Productos existentes": dale clic a la flechita del encabezado "Producto", y arriba del listado hay una cajita "Buscar" que filtra mientras escribes. Copia el nombre que encuentres a la hoja Items.',
            'Departamento / Subfamilia / Unidad de Medida solo se usan si el producto es nuevo (si ya existia, no se tocan).',
            'Si dejas vacios Departamento o Subfamilia en un producto nuevo, queda con "FAMILIA DE PRUEBA" / "SUBFAMILIA DE PRUEBA" (editable despues).',
            'IVA: numero sin el simbolo %. Si lo dejas vacio, usa el IVA que ya tenia el producto (o 19 si es nuevo).',
            'Costo Unitario, Descuento, IVA, Departamento, Subfamilia y Unidad se llenan solos, pero se pueden escribir/cambiar libremente en cualquier fila si esta compra trae un dato distinto (ej: subio el costo).',
            'Todo o nada: si una sola fila tiene error, no se crea la compra ni se toca ningun producto. Corrige el excel y vuelve a subir el MISMO archivo (mismo numero de factura) sin que marque "factura duplicada".',
        ];

        $familias = Familia::where('empresa_id', $this->empresaId)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->values();

        $subfamilias = Subfamilia::where('empresa_id', $this->empresaId)
            ->with('familia')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($s) => $s->nombre . ' (' . (optional($s->familia)->nombre ?? '-') . ')')
            ->values();

        $filas = max(count($instrucciones), $familias->count(), $subfamilias->count());

        $resultado = [];
        for ($i = 0; $i < $filas; $i++) {
            $resultado[] = [
                $instrucciones[$i] ?? '',
                $familias->get($i, ''),
                $subfamilias->get($i, ''),
            ];
        }

        return $resultado;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 70,
            'B' => 28,
            'C' => 32,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '15803D'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('A2:A' . $highestRow)->applyFromArray([
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('B2:C' . $highestRow)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->freezePane('A2');

        return [];
    }

    public function registerEvents(): array
    {
        return [
            // Forzar TODO como texto plano. Si un nombre de departamento o
            // subfamilia empieza con "=", "+", "-" o "@", Excel lo toma
            // como el inicio de una formula (aunque sea una frase normal),
            // y como no es una formula valida, al abrir el archivo Excel
            // "repara" el libro quitando esa celda. Con setValueExplicit
            // como texto, nunca se interpreta como formula.
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                foreach ($sheet->getRowIterator(1, $highestRow) as $fila) {
                    foreach ($fila->getCellIterator('A', $highestColumn) as $celda) {
                        $valor = $celda->getValue();

                        if (is_string($valor) && $valor !== '') {
                            $celda->setValueExplicit($valor, DataType::TYPE_STRING);
                        }
                    }
                }
            },
        ];
    }
}
