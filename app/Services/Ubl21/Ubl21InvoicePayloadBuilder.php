<?php

namespace App\Services\Ubl21;

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Arma el InvoiceRequest (schema del OpenAPI del proveedor) a partir de una
 * Factura -- equivalente a FactusInvoicePayloadBuilder pero para el segundo
 * proveedor (API DIAN UBL 2.1 propia). A diferencia de Factus, este
 * proveedor no requiere resolver ningun ID externo (municipio, etc.): usa
 * directo los codigos DANE que ya estan en ciudades/departamentos.
 */
class Ubl21InvoicePayloadBuilder
{
    public function build(Factura $factura): array
    {
        $factura->loadMissing(['cliente.ciudad.departamento', 'detalles', 'configuracionEmpresa']);
        $configuracion = $factura->configuracionEmpresa;

        if (! $factura->cliente) {
            throw new \InvalidArgumentException('La factura no tiene cliente asociado.');
        }

        if ($factura->detalles->isEmpty()) {
            throw new \InvalidArgumentException('La factura no tiene productos para facturar.');
        }

        if (blank($configuracion?->ubl21_prefix)) {
            throw new \InvalidArgumentException('La empresa no tiene un prefijo UBL 2.1 configurado (ver Empresas > Facturación electrónica).');
        }

        $lineas = $this->lineas($factura);
        $taxTotals = $this->taxTotals($lineas);
        $totalBase = round(array_sum(array_column($lineas, 'line_extension_amount')), 2);
        $totalImpuesto = round(array_sum(array_column($taxTotals, 'tax_amount')), 2);
        $isCredit = $factura->tipo_pago === 'credito';

        return array_filter([
            'type_document_id' => 1,
            'prefix' => $configuracion->ubl21_prefix,
            'resolution_number' => $configuracion->ubl21_resolution_number,
            'issue_date' => $factura->fecha->format('Y-m-d'),
            'issue_time' => $factura->fecha->format('H:i:sP'),
            'due_date' => $isCredit ? optional($factura->fecha_vencimiento ?: $factura->fecha)->format('Y-m-d') : null,
            'currency' => 'COP',
            'customer' => $this->customer($factura->cliente),
            'payment_form' => $this->paymentForm($factura, $isCredit),
            'invoice_lines' => $lineas,
            'tax_totals' => $taxTotals,
            'legal_monetary_total' => [
                'line_extension_amount' => $totalBase,
                'tax_exclusive_amount' => $totalBase,
                'tax_inclusive_amount' => round($totalBase + $totalImpuesto, 2),
                'payable_amount' => round($totalBase + $totalImpuesto, 2),
            ],
            'notes' => Str::limit((string) ($factura->observaciones ?? ''), 250, ''),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function customerFromFactura(Factura $factura): array
    {
        $factura->loadMissing('cliente.ciudad.departamento');

        if (! $factura->cliente) {
            throw new \InvalidArgumentException('La factura no tiene cliente asociado.');
        }

        return $this->customer($factura->cliente);
    }

    private function customer(Actor $cliente): array
    {
        $isJuridica = $cliente->tipo_persona === 'juridica' || (int) $cliente->tipo_documento_id === 6;
        $ciudad = $cliente->ciudad;
        $departamento = $ciudad?->departamento;

        return array_filter([
            'identification_number' => preg_replace('/\D/', '', (string) $cliente->identificacion) ?: '222222222222',
            'name' => $isJuridica ? null : ($cliente->nombre ?: $cliente->razon_social),
            'company_name' => $isJuridica ? ($cliente->razon_social ?: $cliente->nombre) : null,
            'phone' => $cliente->telefono ?: '3000000000',
            'address' => $cliente->direccion ?: 'Sin direccion',
            'email' => $cliente->email ?: 'cliente@example.com',
            'type_document_identification_code' => Ubl21DianCatalog::tipoDocumentoIdentificacion((int) $cliente->tipo_documento_id),
            'type_organization_id' => $isJuridica ? 1 : 2,
            // Codigos DANE directos (5 digitos municipio, 2 digitos
            // departamento) -- no hace falta resolver ningun ID via API
            // como con Factus, ya estan en ciudades/departamentos.
            'municipality_code' => $ciudad ? str_pad((string) $ciudad->codigo_dian, 5, '0', STR_PAD_LEFT) : '11001',
            'department_code' => $departamento ? str_pad((string) $departamento->codigo_dian, 2, '0', STR_PAD_LEFT) : '11',
            'country_code' => 'CO',
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function paymentForm(Factura $factura, bool $isCredit): array
    {
        return array_filter([
            'payment_form_code' => $isCredit ? '2' : '1',
            'payment_method_code' => Ubl21DianCatalog::medioPago((string) $factura->medio_pago),
            'payment_due_date' => $isCredit ? optional($factura->fecha_vencimiento ?: $factura->fecha)->format('Y-m-d') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array{descripcion: array, tax_totals: array}>
     */
    private function lineas(Factura $factura): array
    {
        return $factura->detalles->map(function ($detalle) use ($factura) {
            $producto = Product::where('empresa_id', $factura->empresa_id)
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

    /**
     * Agrupa los tax_totals de cada linea por tax_id, para el resumen a
     * nivel de factura que tambien pide el schema (InvoiceRequest.tax_totals).
     */
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
