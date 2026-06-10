<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InventarioGeneralExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected string $nombreAlmacen = 'Almacen',
    ) {
    }

    public function query(): Builder
    {
        return Product::query()
            ->with(['proveedor', 'familia1', 'subfamilia', 'cuentaContable'])
            ->where('empresa_id', $this->empresaId)
            ->orderBy('existencias');
    }

    public function headings(): array
    {
        return [
            'Codigo',
            'Producto',
            'Cuenta contable',
            'Existencias positivas',
            'Existencias negativas',
            'Costo unitario',
            'IVA compra %',
            'Valor costo',
            'Valor IVA compra',
            'Total costo',
            'IVA ventas %',
            'Valor venta unitario',
            'Total ventas',
            'Valor ventas por existencias',
        ];
    }

    public function map($product): array
    {
        $stock = (float) $product->existencias;
        $positive = max($stock, 0);
        $negative = abs(min($stock, 0));
        $costUnit = (float) $product->precio_costo;
        $ivaCompra = (float) ($product->iva_compra ?? 0);
        $ivaVenta = (float) ($product->iva_venta ?? 0);
        $saleUnit = (float) $product->precio_venta1;
        $valorCosto = round($stock * $costUnit, 2);
        $valorIvaCompra = round($valorCosto * ($ivaCompra / 100), 2);
        $totalCosto = round($valorCosto + $valorIvaCompra, 2);
        $valorIvaVenta = round($saleUnit * ($ivaVenta / 100), 2);
        $totalVentas = round($stock * $saleUnit, 2);
        $valorVentasConIva = round($totalVentas + ($stock * $valorIvaVenta), 2);

        return [
            $product->id_producto,
            $product->descripcion_larga,
            $product->cuentaContable?->codigo ?? null,
            $positive,
            $negative,
            $costUnit,
            $ivaCompra,
            $valorCosto,
            $valorIvaCompra,
            $totalCosto,
            $ivaVenta,
            $saleUnit,
            $totalVentas,
            $valorVentasConIva,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => '"$" #,##0.00',
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => '"$" #,##0.00',
            'I' => '"$" #,##0.00',
            'J' => '"$" #,##0.00',
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'L' => '"$" #,##0.00',
            'M' => '"$" #,##0.00',
            'N' => '"$" #,##0.00',
        ];
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = $sheet->getHighestRow();
                $totalsRow = $lastDataRow + 2;
                $lastColumn = Coordinate::stringFromColumnIndex(14);

                $sheet->mergeCells('A1:N1');
                $sheet->mergeCells('A2:N2');
                $sheet->setCellValue('A1', strtoupper($this->nombreAlmacen));
                $sheet->setCellValue('A2', 'Inventario General de Productos - ' . now()->format('d/m/Y H:i'));
                $sheet->freezePane('A5');
                $sheet->setAutoFilter('A4:' . $lastColumn . $lastDataRow);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(24);

                $sheet->getStyle('A1:N1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A2:N2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => '334155'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0F2FE'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A4:N4')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '1E3A8A'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A4:' . $lastColumn . $lastDataRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A5:A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C5:C' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D5:N' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A5:' . $lastColumn . $lastDataRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->setCellValue('C' . $totalsRow, 'Totales');
                $sheet->setCellValue('D' . $totalsRow, '=SUM(D5:D' . $lastDataRow . ')');
                $sheet->setCellValue('E' . $totalsRow, '=SUM(E5:E' . $lastDataRow . ')');
                $sheet->setCellValue('H' . $totalsRow, '=SUM(H5:H' . $lastDataRow . ')');
                $sheet->setCellValue('I' . $totalsRow, '=SUM(I5:I' . $lastDataRow . ')');
                $sheet->setCellValue('J' . $totalsRow, '=SUM(J5:J' . $lastDataRow . ')');
                $sheet->setCellValue('M' . $totalsRow, '=SUM(M5:M' . $lastDataRow . ')');
                $sheet->setCellValue('N' . $totalsRow, '=SUM(N5:N' . $lastDataRow . ')');

                $sheet->getRowDimension($totalsRow)->setRowHeight(24);
                $sheet->getStyle('C' . $totalsRow . ':N' . $totalsRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '0F172A'],
                        ],
                    ],
                ]);

                foreach (['H', 'I', 'J', 'M', 'N'] as $col) {
                    $sheet->getStyle($col . $totalsRow . ':' . $col . $totalsRow)
                        ->getNumberFormat()
                        ->setFormatCode('"$" #,##0.00');
                }
            },
        ];
    }
}
