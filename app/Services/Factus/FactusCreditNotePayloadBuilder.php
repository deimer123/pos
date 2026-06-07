<?php

namespace App\Services\Factus;

use App\Models\Devolucion;
use App\Models\Product;
use Illuminate\Support\Str;

class FactusCreditNotePayloadBuilder
{
    public function build(Devolucion $devolucion): array
    {
        $devolucion->loadMissing(['factura.configuracionEmpresa', 'detalles']);

        $factura = $devolucion->factura;
        $configuracion = $factura?->configuracionEmpresa;

        if (! $factura) {
            throw new \InvalidArgumentException('La devolucion no tiene factura asociada.');
        }

        if ($factura->tipo_factura !== 'electronica') {
            throw new \InvalidArgumentException('La factura origen no es electronica.');
        }

        if (blank($factura->factus_bill_id)) {
            throw new \InvalidArgumentException('La factura electronica no tiene ID de Factus para generar la nota credito.');
        }

        if ($devolucion->detalles->isEmpty()) {
            throw new \InvalidArgumentException('La devolucion no tiene items para reportar como nota credito.');
        }

        $referenceCode = $devolucion->factus_credit_note_reference_code ?: 'NC-POS-'.$devolucion->empresa_id.'-'.$devolucion->id;

        return array_filter([
            'numbering_range_id' => $configuracion?->factus_credit_note_numbering_range_id
                ? (int) $configuracion->factus_credit_note_numbering_range_id
                : null,
            'correction_concept_code' => 1,
            'customization_id' => 20,
            'bill_id' => (int) $factura->factus_bill_id,
            'reference_code' => $referenceCode,
            'payment_method_code' => $this->paymentMethodCode((string) $factura->medio_pago),
            'send_email' => (bool) ($configuracion?->factus_send_email ?? false),
            'observation' => Str::limit((string) ($devolucion->observaciones ?: 'Devolucion de mercancia'), 250, ''),
            'items' => $this->items($devolucion),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function items(Devolucion $devolucion): array
    {
        return $devolucion->detalles->map(function ($detalle) use ($devolucion) {
            $producto = Product::where('empresa_id', $devolucion->empresa_id)
                ->where('id_producto', $detalle->producto_id)
                ->first();

            $taxRate = (float) ($producto?->iva_venta ?? 0);
            $priceWithTax = (float) $detalle->precio;
            $basePrice = $taxRate > 0
                ? round($priceWithTax / (1 + ($taxRate / 100)), 2)
                : round($priceWithTax, 2);

            return [
                'code_reference' => (string) $detalle->producto_id,
                'name' => Str::limit((string) ($detalle->descripcion_larga ?: $producto?->descripcion_larga ?: 'Producto'), 250, ''),
                'quantity' => $this->decimal($detalle->cantidad),
                'discount_rate' => '0.00',
                'price' => $this->money($basePrice),
                'tax_rate' => $this->decimal($taxRate),
                'unit_measure_id' => 70,
                'standard_code_id' => 1,
                'is_excluded' => 0,
                'tribute_id' => 1,
                'withholding_taxes' => [],
            ];
        })->values()->all();
    }

    private function paymentMethodCode(string $medioPago): string
    {
        return match (strtolower($medioPago)) {
            'transferencia' => '42',
            default => '10',
        };
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
