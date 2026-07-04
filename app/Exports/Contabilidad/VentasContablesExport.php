<?php

namespace App\Exports\Contabilidad;

use App\Models\Factura;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasContablesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected ?string $desde = null,
        protected ?string $hasta = null,
        protected ?string $tipo = null,
    ) {
    }

    public function collection(): Collection
    {
        return Factura::query()
            ->with(['cliente', 'detalles.producto'])
            ->where('empresa_id', $this->empresaId)
            ->when($this->desde, fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->when($this->tipo, fn ($q) => $q->where('tipo_factura', $this->tipo))
            ->orderByDesc('fecha')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Factura',
            'Fecha',
            'Cliente',
            'Tipo',
            'Ventas productos c/IVA (4135)',
            'Ventas productos s/IVA (4135)',
            'IVA',
            'Otros servicios (4200)',
            'Reembolso a terceros (no es venta)',
            'Total',
            'Saldo',
        ];
    }

    public function map($factura): array
    {
        $ventasConIva  = 0.0;
        $ventasSinIva  = 0.0;
        $ivaTotal      = 0.0;
        $servicios     = 0.0;
        $passthrough   = 0.0;

        foreach ($factura->detalles as $detalle) {
            $base   = (float) $detalle->subtotal;
            $ivaPct = (float) ($detalle->producto?->iva_venta ?? 0);
            $esServicio = ($detalle->producto?->tipo_producto ?? '') === 'servicio';
            $esPassthrough = (string) $detalle->producto_id === '10001';

            if ($esPassthrough) {
                // Item manual: reembolso a terceros sin margen, no es venta ni servicio propio.
                $passthrough += $base;
            } elseif ($esServicio) {
                $servicios += $base;
            } elseif ($ivaPct > 0) {
                $ventasConIva += $base;
                $ivaTotal += round($base * ($ivaPct / 100), 2);
            } else {
                $ventasSinIva += $base;
            }
        }

        return [
            $factura->numero_visual,
            optional($factura->fecha)->format('d/m/Y H:i'),
            $factura->cliente?->nombre ?? 'Consumidor final',
            $factura->tipo_factura,
            round($ventasConIva, 2),
            round($ventasSinIva, 2),
            round($ivaTotal, 2),
            round($servicios, 2),
            round($passthrough, 2),
            (float) $factura->total,
            (float) $factura->saldo,
        ];
    }
}
