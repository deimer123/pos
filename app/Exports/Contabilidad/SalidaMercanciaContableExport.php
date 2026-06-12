<?php

namespace App\Exports\Contabilidad;

use App\Models\FacturaDetalle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalidaMercanciaContableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected int $empresaId,
        protected ?string $desde = null,
        protected ?string $hasta = null,
    ) {
    }

    public function collection(): Collection
    {
        return FacturaDetalle::query()
            ->with(['factura.cliente', 'producto.cuentaContable'])
            ->whereHas('factura', function ($q) {
                $q->where('empresa_id', $this->empresaId)
                    ->where('tipo_factura', 'salida')
                    ->when($this->desde, fn ($qq) => $qq->whereDate('fecha', '>=', $this->desde))
                    ->when($this->hasta, fn ($qq) => $qq->whereDate('fecha', '<=', $this->hasta));
            })
            ->orderByDesc('id')
            ->get();
    }

    public function headings(): array
    {
        return ['Factura', 'Fecha', 'Cliente', 'Código', 'Producto', 'Cuenta contable', 'Cantidad', 'Precio', 'Subtotal'];
    }

    public function map($detalle): array
    {
        return [
            $detalle->factura?->numero_visual,
            optional($detalle->factura?->fecha)->format('d/m/Y H:i'),
            $detalle->factura?->cliente?->nombre ?? 'Consumidor final',
            $detalle->producto?->id_producto,
            $detalle->descripcion_larga ?? $detalle->producto?->descripcion_larga,
            $detalle->producto?->cuentaContable?->codigo,
            (float) $detalle->cantidad,
            (float) $detalle->precio,
            (float) $detalle->subtotal,
        ];
    }
}
