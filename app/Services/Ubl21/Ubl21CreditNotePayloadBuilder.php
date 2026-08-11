<?php

namespace App\Services\Ubl21;

use App\Models\Devolucion;
use App\Models\Product;
use Illuminate\Support\Str;

class Ubl21CreditNotePayloadBuilder
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

        if (blank($factura->ubl21_cufe) || blank($factura->ubl21_document_number)) {
            throw new \InvalidArgumentException('La factura electronica no tiene CUFE/numero UBL 2.1 para generar la nota credito.');
        }

        if ($devolucion->detalles->isEmpty()) {
            throw new \InvalidArgumentException('La devolucion no tiene items para reportar como nota credito.');
        }

        $prefijo = $configuracion?->ubl21_credit_note_prefix ?: $configuracion?->ubl21_prefix;

        if (blank($prefijo)) {
            throw new \InvalidArgumentException('La empresa no tiene un prefijo UBL 2.1 configurado para notas credito.');
        }

        $lineas = $this->lineas($devolucion);
        $taxTotals = $this->taxTotals($lineas);
        $totalBase = round(array_sum(array_column($lineas, 'line_extension_amount')), 2);
        $totalImpuesto = round(array_sum(array_column($taxTotals, 'tax_amount')), 2);

        return array_filter([
            'type_document_id' => 4,
            'prefix' => $prefijo,
            'resolution_number' => $configuracion?->ubl21_credit_note_resolution_number ?: $configuracion?->ubl21_resolution_number,
            'issue_date' => now()->format('Y-m-d'),
            'issue_time' => now()->format('H:i:sP'),
            'currency' => 'COP',
            'customer' => (new Ubl21InvoicePayloadBuilder())->customerFromFactura($factura),
            'payment_form' => [
                'payment_form_code' => '1',
                'payment_method_code' => Ubl21DianCatalog::medioPago((string) $factura->medio_pago),
            ],
            'invoice_lines' => $lineas,
            'tax_totals' => $taxTotals,
            'legal_monetary_total' => [
                'line_extension_amount' => $totalBase,
                'tax_exclusive_amount' => $totalBase,
                'tax_inclusive_amount' => round($totalBase + $totalImpuesto, 2),
                'payable_amount' => round($totalBase + $totalImpuesto, 2),
            ],
            'billing_reference' => [
                'number' => (string) $factura->ubl21_document_number,
                'uuid' => (string) $factura->ubl21_cufe,
                'issue_date' => $factura->fecha->format('Y-m-d'),
            ],
            'discrepancy_response' => [
                'code' => '2',
                'description' => Str::limit((string) ($devolucion->observaciones ?: 'Anulacion / devolucion de mercancia'), 250, ''),
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function lineas(Devolucion $devolucion): array
    {
        return $devolucion->detalles->map(function ($detalle) use ($devolucion) {
            $producto = Product::where('empresa_id', $devolucion->empresa_id)
                ->where('id_producto', $detalle->producto_id)
                ->first();

            $taxRate = (float) ($producto?->iva_venta ?? 0);
            $priceWithTax = (float) $detalle->precio;
            $basePrice = $taxRate > 0 ? round($priceWithTax / (1 + $taxRate / 100), 2) : round($priceWithTax, 2);
            $cantidad = (float) $detalle->cantidad;
            $baseLinea = round($basePrice * $cantidad, 2);
            $impuestoLinea = round($baseLinea * $taxRate / 100, 2);

            return [
                'unit_measure_code' => '94',
                'invoiced_quantity' => $cantidad,
                'line_extension_amount' => $baseLinea,
                'description' => Str::limit((string) ($detalle->descripcion_larga ?: $producto?->descripcion_larga ?: 'Producto'), 250, ''),
                'code' => (string) $detalle->producto_id,
                'price_amount' => $basePrice,
                'tax_totals' => $taxRate > 0 ? [[
                    'tax_id' => '01',
                    'tax_name' => 'IVA',
                    'tax_amount' => $impuestoLinea,
                    'taxable_amount' => $baseLinea,
                    'percent' => $taxRate,
                ]] : [],
            ];
        })->values()->all();
    }

    private function taxTotals(array $lineas): array
    {
        $agrupado = [];

        foreach ($lineas as $linea) {
            foreach ($linea['tax_totals'] as $tax) {
                $key = $tax['tax_id'];

                if (! isset($agrupado[$key])) {
                    $agrupado[$key] = [
                        'tax_id' => $tax['tax_id'],
                        'tax_name' => $tax['tax_name'],
                        'tax_amount' => 0.0,
                        'taxable_amount' => 0.0,
                        'percent' => $tax['percent'],
                    ];
                }

                $agrupado[$key]['tax_amount'] += $tax['tax_amount'];
                $agrupado[$key]['taxable_amount'] += $tax['taxable_amount'];
            }
        }

        return array_values(array_map(fn ($t) => [
            'tax_id' => $t['tax_id'],
            'tax_name' => $t['tax_name'],
            'tax_amount' => round($t['tax_amount'], 2),
            'taxable_amount' => round($t['taxable_amount'], 2),
            'percent' => $t['percent'],
        ], $agrupado));
    }
}
