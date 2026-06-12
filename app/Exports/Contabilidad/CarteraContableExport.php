<?php

namespace App\Exports\Contabilidad;

use App\Models\Factura;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CarteraContableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected ?string $desde = null,
        protected ?string $hasta = null,
    ) {
    }

    public function collection(): Collection
    {
        return Factura::query()
            ->with(['cliente', 'pagos'])
            ->where('empresa_id', $this->empresaId)
            ->where('tipo_pago', 'credito')
            ->when($this->desde, fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    public function headings(): array
    {
        return ['Factura', 'Fecha', 'Cliente', 'Vence', 'Total', 'Pagado', 'Saldo'];
    }

    public function map($factura): array
    {
        return [
            $factura->numero_visual,
            optional($factura->fecha)->format('d/m/Y H:i'),
            $factura->cliente?->nombre ?? 'Consumidor final',
            optional($factura->fecha_vencimiento)->format('d/m/Y'),
            (float) $factura->total,
            (float) $factura->pagos->sum('monto'),
            (float) $factura->saldo,
        ];
    }
}
