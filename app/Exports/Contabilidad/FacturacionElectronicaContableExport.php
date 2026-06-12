<?php

namespace App\Exports\Contabilidad;

use App\Models\Factura;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FacturacionElectronicaContableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
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
            ->with(['cliente'])
            ->where('empresa_id', $this->empresaId)
            ->where('tipo_factura', 'electronica')
            ->when($this->desde, fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->orderByDesc('fecha')
            ->get();
    }

    public function headings(): array
    {
        return ['Factura', 'Fecha', 'Cliente', 'Factus #', 'CUFE', 'Estado', 'Total', 'Saldo'];
    }

    public function map($factura): array
    {
        return [
            $factura->numero_visual,
            optional($factura->fecha)->format('d/m/Y H:i'),
            $factura->cliente?->nombre ?? 'Consumidor final',
            $factura->factus_number,
            $factura->factus_cufe,
            $factura->factus_status,
            (float) $factura->total,
            (float) $factura->saldo,
        ];
    }
}
