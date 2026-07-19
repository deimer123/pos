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
            ->with(['proveedor', 'familia1', 'subfamilia'])
            ->where('empresa_id', $this->empresaId)
            ->orderBy('existencias');
    }

    public function headings(): array
    {
        return [
            'Codigo',
            'Producto',
            'Proveedor',
            'Familia',
            'Subfamilia',
            'Stock',
            'Costo',
            'Venta',
            'IVA %',
            'Costo Total',
            'Utilidad %',
            'Utilidad $',
        ];
    }

    // Si un nombre de producto quedo guardado empezando como formula
    // (=, +, -, @), Excel/LibreOffice lo ejecutaria como formula real al
    // abrir este reporte. Se neutraliza igual que en las demas plantillas
    // de exportacion (ver CompraBulkTemplateProductosExistentesSheet).
    protected function textoSeguro(?string $valor): string
    {
        if (! $valor || preg_match('/^[=+\-@]/', $valor)) {
            return '-';
        }

        return $valor;
    }

    public function map($product): array
    {
        $costWithTax = $this->unitCostWithTax($product);
        $salePrice = (float) $product->precio_venta1;
        $utilityPercent = $salePrice > 0
            ? round((($salePrice - $costWithTax) / $salePrice) * 100, 2)
            : 0;

        return [
            $product->id_producto,
            $this->textoSeguro($product->descripcion_larga),
            $product->proveedor?->nombre,
            $product->familia1?->nombre,
            $product->subfamilia?->nombre,
            (float) $product->existencias,
            (float) $product->precio_costo,
            $salePrice,
            (float) $product->iva_venta,
            $costWithTax,
            $utilityPercent,
            round($salePrice - $costWithTax, 2),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => '"$" #,##0.00',
            'H' => '"$" #,##0.00',
            'I' => NumberFormat::FORMAT_NUMBER_00,
            'J' => '"$" #,##0.00',
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'L' => '"$" #,##0.00',
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
                $lastColumn = $sheet->getHighestColumn();

                $sheet->mergeCells('A1:L1');
                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A1', strtoupper($this->nombreAlmacen));
                $sheet->setCellValue('A2', 'Inventario General de Productos - ' . now()->format('d/m/Y H:i'));
                $sheet->freezePane('A5');
                $sheet->setAutoFilter('A4:' . $lastColumn . $lastDataRow);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(24);

                $sheet->getStyle('A1:L1')->applyFromArray([
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

                $sheet->getStyle('A2:L2')->applyFromArray([
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

                $sheet->getStyle('A4:L4')->applyFromArray([
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
                $sheet->getStyle('F5:F' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G5:L' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('A5:' . $lastColumn . $lastDataRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->setCellValue('G' . $totalsRow, 'Total Costo');
                $sheet->setCellValue('H' . $totalsRow, '=SUMPRODUCT(F5:F' . $lastDataRow . ',J5:J' . $lastDataRow . ')');
                $sheet->setCellValue('I' . $totalsRow, 'Total Ventas');
                $sheet->setCellValue('J' . $totalsRow, '=SUMPRODUCT(F5:F' . $lastDataRow . ',H5:H' . $lastDataRow . ')');
                $sheet->setCellValue('K' . $totalsRow, 'Total Utilidades');
                $sheet->setCellValue('L' . $totalsRow, '=J' . $totalsRow . '-H' . $totalsRow);

                $sheet->getRowDimension($totalsRow)->setRowHeight(24);
                $sheet->getStyle('G' . $totalsRow . ':L' . $totalsRow)->applyFromArray([
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

                $sheet->getStyle('H' . $totalsRow . ':H' . $totalsRow)->getNumberFormat()->setFormatCode('"$" #,##0.00');
                $sheet->getStyle('J' . $totalsRow . ':J' . $totalsRow)->getNumberFormat()->setFormatCode('"$" #,##0.00');
                $sheet->getStyle('L' . $totalsRow . ':L' . $totalsRow)->getNumberFormat()->setFormatCode('"$" #,##0.00');
            },
        ];
    }

    protected function unitCostWithTax(Product $product): float
    {
        $cost = (float) $product->precio_costo;
        $discount = (float) ($product->descuento_comercial ?? 0);
        $tax = (float) ($product->iva_venta ?? 0);
        $discountedCost = $cost * (1 - ($discount / 100));

        return round($discountedCost * (1 + ($tax / 100)), 2);
    }
}
