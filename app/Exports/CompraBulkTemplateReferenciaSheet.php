<?php

namespace App\Exports;

use App\Models\Familia;
use App\Models\Subfamilia;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompraBulkTemplateReferenciaSheet implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
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
            'En la columna Producto (hoja Items), al pararte en la celda aparece una flechita a la derecha: dale clic para ver y elegir los productos que ya tiene el proveedor que elegiste en la pantalla antes de descargar.',
            'Revisa la hoja "Productos existentes" para ver el Costo, Descuento, IVA, Utilidad, Precio de Venta, Departamento, Subfamilia y Unidad de Medida que tiene ACTUALMENTE cada producto de ese proveedor, antes de decidir que escribir.',
            'Departamento / Subfamilia / Unidad de Medida solo se usan si el producto es nuevo (si ya existia, no se tocan).',
            'Si dejas vacios Departamento o Subfamilia en un producto nuevo, queda con "FAMILIA DE PRUEBA" / "SUBFAMILIA DE PRUEBA" (editable despues).',
            'IVA: numero sin el simbolo %. Si lo dejas vacio, usa el IVA que ya tenia el producto (o 19 si es nuevo).',
            'Precio de Venta: si lo dejas vacio, se calcula solo con el costo + la Utilidad. Si tambien dejas Utilidad vacia, se usa la utilidad que ya tenia el producto (o 30% si es nuevo).',
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
}
