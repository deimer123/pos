<?php

namespace App\Exports\Contabilidad;

use App\Models\Compra;
use App\Models\ConfiguracionEmpresa;
use App\Models\CuentaContable;
use App\Models\Factura;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LibroInventariosBalanceExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    protected string $nombreEmpresa;

    protected string $nitEmpresa;

    protected string $periodo;

    protected float $inventario = 0.0;

    protected float $cartera = 0.0;

    protected float $proveedores = 0.0;

    protected float $ventasContado = 0.0;

    protected float $ivaVentas = 0.0;

    public function __construct(
        protected int $empresaId,
        protected ?string $desde = null,
        protected ?string $hasta = null,
    )
    {
        $configuracion = ConfiguracionEmpresa::query()
            ->where('empresa_id', $this->empresaId)
            ->first();

        $this->nombreEmpresa = $configuracion?->nombre_empresa
            ?? auth()->user()?->name
            ?? 'Empresa';
        $this->nitEmpresa = $configuracion?->nit ?: 'N/A';
        $this->periodo = $this->formatearPeriodo();
        $this->inventario = $this->inventarioValorizado();
        $this->cartera = $this->carteraPendiente();
        $this->proveedores = $this->cuentasPorPagar();
        $this->ventasContado = $this->ventasContado();
        $this->ivaVentas = $this->ivaVentas();
    }

    public function collection(): Collection
    {
        return CuentaContable::query()
            ->where('empresa_id', $this->empresaId)
            ->orderBy('codigo')
            ->get()
            ->map(function (CuentaContable $cuenta): array {
                $balance = $this->balanceParaCuenta($cuenta);
                $esDetalle = mb_strlen((string) $cuenta->codigo) > 4 || str_ends_with((string) $cuenta->codigo, '01');

                return [
                    'codigo' => (string) $cuenta->codigo,
                    'nombre' => (string) $cuenta->nombre,
                    'parcial' => $esDetalle ? $balance : 0,
                    'saldo' => $esDetalle ? 0 : $balance,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Código cuenta contable',
            'Nombre',
            'Parcial',
            'Saldo',
        ];
    }

    public function map($row): array
    {
        return [
            $row['codigo'],
            $row['nombre'],
            $row['parcial'],
            $row['saldo'],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '"$" #,##0.00',
            'D' => '"$" #,##0.00',
        ];
    }

    public function startCell(): string
    {
        return 'A8';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = $sheet->getHighestRow();
                $totalsRow = $lastDataRow + 2;
                $lastColumn = 'D';

                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');
                $sheet->mergeCells('A3:D3');
                $sheet->mergeCells('A4:D4');

                $sheet->setCellValue('A1', 'Libro de inventarios y balance');
                $sheet->setCellValue('A2', strtoupper($this->nombreEmpresa));
                $sheet->setCellValue('A3', $this->nitEmpresa);
                $sheet->setCellValue('A4', 'Periodo: ' . $this->periodo);

                $sheet->freezePane('A9');
                $sheet->setAutoFilter('A8:' . $lastColumn . $lastDataRow);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(20);
                $sheet->getRowDimension(8)->setRowHeight(24);

                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0EA5E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A2:D4')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0EA5E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A8:D8')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'],
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

                if ($lastDataRow >= 9) {
                    $sheet->getStyle('A8:D' . $lastDataRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CBD5E1'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle('A9:A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C9:D' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('A9:D' . $lastDataRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->setCellValue('A' . $totalsRow, 'Totales');
                if ($lastDataRow >= 9) {
                    $sheet->setCellValue('C' . $totalsRow, '=SUM(C9:C' . $lastDataRow . ')');
                    $sheet->setCellValue('D' . $totalsRow, '=SUM(D9:D' . $lastDataRow . ')');
                } else {
                    $sheet->setCellValue('C' . $totalsRow, 0);
                    $sheet->setCellValue('D' . $totalsRow, 0);
                }

                $sheet->getStyle('A' . $totalsRow . ':D' . $totalsRow)->applyFromArray([
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

                $sheet->getStyle('C' . $totalsRow . ':D' . $totalsRow)
                    ->getNumberFormat()
                    ->setFormatCode('"$" #,##0.00');
            },
        ];
    }

    protected function balanceParaCuenta(CuentaContable $cuenta): float
    {
        $codigo = (string) $cuenta->codigo;
        $nombre = mb_strtolower((string) $cuenta->nombre);

        return match (true) {
            str_starts_with($codigo, '14') || str_contains($nombre, 'inventario') => $this->inventario,
            str_starts_with($codigo, '13') || str_contains($nombre, 'cliente') || str_contains($nombre, 'deudor') => $this->cartera,
            str_starts_with($codigo, '22') || str_starts_with($codigo, '23') || str_contains($nombre, 'proveedor') || str_contains($nombre, 'acreed') => -$this->proveedores,
            str_starts_with($codigo, '11') || str_contains($nombre, 'caja') || str_contains($nombre, 'banco') => $this->ventasContado,
            str_contains($nombre, 'iva') => $this->ivaVentas,
            str_contains($nombre, 'venta') || str_contains($nombre, 'ingreso') => $this->ventasContado,
            default => 0.0,
        };
    }

    protected function inventarioValorizado(): float
    {
        return (float) Product::query()
            ->where('empresa_id', $this->empresaId)
            ->selectRaw('COALESCE(SUM(COALESCE(existencias, 0) * COALESCE(precio_costo, 0)), 0) as total')
            ->value('total');
    }

    protected function carteraPendiente(): float
    {
        return (float) Factura::query()
            ->where('empresa_id', $this->empresaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->creditoPendiente()
            ->sum('saldo');
    }

    protected function cuentasPorPagar(): float
    {
        return (float) Compra::query()
            ->where('empresa_id', $this->empresaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->where('tipo_pago', 'credito')
            ->sum('saldo');
    }

    protected function ventasContado(): float
    {
        return (float) Factura::query()
            ->where('empresa_id', $this->empresaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->where('tipo_pago', 'contado')
            ->sum('total');
    }

    protected function ivaVentas(): float
    {
        return (float) Factura::query()
            ->where('empresa_id', $this->empresaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->with('detalles.producto')
            ->get()
            ->sum(function (Factura $factura): float {
                return (float) $factura->detalles->sum(function ($detalle): float {
                    $ivaPct = (float) ($detalle->producto?->iva_venta ?? 0);

                    return round(((float) $detalle->subtotal) * ($ivaPct / 100), 2);
                });
            });
    }

    protected function formatearPeriodo(): string
    {
        if ($this->desde && $this->hasta) {
            return Carbon::parse($this->desde)->format('d/m/Y') . ' - ' . Carbon::parse($this->hasta)->format('d/m/Y');
        }

        if ($this->desde) {
            return 'Desde ' . Carbon::parse($this->desde)->format('d/m/Y');
        }

        if ($this->hasta) {
            return 'Hasta ' . Carbon::parse($this->hasta)->format('d/m/Y');
        }

        return now()->locale('es')->translatedFormat('F Y');
    }
}
