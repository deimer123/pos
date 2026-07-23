<?php

namespace App\Exports;

use App\Models\LiquidacionMecanico;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LiquidacionesComisionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected string $rol,
        protected ?string $desde = null,
        protected ?string $hasta = null,
    ) {
    }

    public function collection(): Collection
    {
        return LiquidacionMecanico::query()
            ->with('mecanico')
            ->where('empresa_id', $this->empresaId)
            ->whereHas('mecanico', fn ($q) => $q->where('rol', $this->rol))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_pago', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_pago', '<=', $this->hasta))
            ->orderByDesc('fecha_pago')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Periodo desde',
            'Periodo hasta',
            'Total ventas/servicios',
            '% comisión',
            'Monto comisión',
            'Préstamos descontados',
            'Monto neto pagado',
            'Fecha de pago',
            'Medio de pago',
        ];
    }

    public function map($liquidacion): array
    {
        return [
            $liquidacion->mecanico?->nombre,
            optional($liquidacion->fecha_desde)->format('d/m/Y'),
            optional($liquidacion->fecha_hasta)->format('d/m/Y'),
            (float) $liquidacion->total_servicios,
            (float) $liquidacion->porcentaje_mecanico,
            (float) $liquidacion->monto_mecanico,
            (float) $liquidacion->prestamos_descontados,
            (float) $liquidacion->monto_neto,
            optional($liquidacion->fecha_pago)->format('d/m/Y'),
            $liquidacion->medio_pago,
        ];
    }
}
