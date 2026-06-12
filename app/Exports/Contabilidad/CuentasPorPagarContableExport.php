<?php

namespace App\Exports\Contabilidad;

use App\Models\Compra;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CuentasPorPagarContableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected ?string $desde = null,
        protected ?string $hasta = null,
    ) {
    }

    public function collection(): Collection
    {
        return Compra::query()
            ->with(['proveedor', 'pagos'])
            ->where('empresa_id', $this->empresaId)
            ->where('tipo_pago', 'credito')
            ->when($this->desde, fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    public function headings(): array
    {
        return ['Compra', 'Fecha', 'Proveedor', 'Vence', 'Total', 'Pagado', 'Saldo'];
    }

    public function map($compra): array
    {
        return [
            $compra->numero_factura ?? ('COMP-' . str_pad($compra->id, 6, '0', STR_PAD_LEFT)),
            optional($compra->fecha)->format('d/m/Y'),
            $compra->proveedor?->nombre ?? '-',
            optional($compra->fecha_vencimiento)->format('d/m/Y'),
            (float) $compra->total,
            (float) $compra->pagos->sum('monto'),
            (float) $compra->saldo,
        ];
    }
}
