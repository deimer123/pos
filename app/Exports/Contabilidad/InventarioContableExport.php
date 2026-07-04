<?php

namespace App\Exports\Contabilidad;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InventarioContableExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    public function __construct(protected int $empresaId)
    {
    }

    public function collection(): Collection
    {
        return Product::query()
            ->with('cuentaContable')
            ->where('empresa_id', $this->empresaId)
            ->where('id_producto', '!=', '10001')
            ->orderBy('id_producto')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombre',
            'Cuenta contable',
            'Existencias',
            'Costo unitario',
            'IVA compra',
            'Costo + IVA',
            'IVA venta',
            'Valor venta',
            'Valor venta x existencias',
        ];
    }

    public function map($product): array
    {
        $costo = (float) $product->precio_costo;
        $ivaCompra = (float) ($product->iva_compra ?? 0);
        $ivaVenta = (float) ($product->iva_venta ?? 0);
        $existencias = (float) $product->existencias;
        $costoConIva = round($costo * (1 + ($ivaCompra / 100)), 2);
        $valorVenta = (float) $product->precio_venta1;

        return [
            $product->id_producto,
            $product->descripcion_larga,
            $product->cuentaContable?->codigo,
            $existencias,
            $costo,
            $ivaCompra,
            $costoConIva,
            $ivaVenta,
            $valorVenta,
            round($valorVenta * $existencias, 2),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => '"$" #,##0.00',
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => '"$" #,##0.00',
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => '"$" #,##0.00',
            'J' => '"$" #,##0.00',
        ];
    }
}
