<?php

namespace App\Services\Factus;

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FactusInvoicePayloadBuilder
{
    public function __construct(
        private readonly FactusClient $factus,
    ) {
    }

    public function build(Factura $factura): array
    {
        $factura->loadMissing(['cliente.ciudad', 'detalles', 'configuracionEmpresa']);
        $configuracion = $factura->configuracionEmpresa;
        $factus = $this->clientFor($configuracion);

        if (! $factura->cliente) {
            throw new \InvalidArgumentException('La factura no tiene cliente asociado.');
        }

        if ($factura->detalles->isEmpty()) {
            throw new \InvalidArgumentException('La factura no tiene productos para facturar.');
        }

        $referenceCode = $factura->factus_reference_code ?: 'POS-'.$factura->empresa_id.'-'.$factura->id;

        return array_filter([
            'reference_code' => $referenceCode,
            'document' => '01',
            'numbering_range_id' => $this->numberingRangeId($configuracion),
            'send_email' => $this->shouldSendEmail($configuracion),
            'observation' => Str::limit((string) ($factura->observaciones ?? ''), 250, ''),
            'payment_details' => [$this->paymentDetail($factura)],
            'customer' => $this->customer($factura->cliente, $factus, $this->shouldSendEmail($configuracion)),
            'items' => $this->items($factura),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function paymentDetail(Factura $factura): array
    {
        $isCredit = $factura->tipo_pago === 'credito';

        return array_filter([
            'payment_form' => $isCredit ? '2' : '1',
            'payment_method_code' => $this->paymentMethodCode((string) $factura->medio_pago),
            'reference_code' => 'PAGO-'.$factura->id,
            'amount' => $this->money($factura->total),
            'due_date' => $isCredit
                ? optional($factura->fecha_vencimiento ?: $factura->fecha)->format('Y-m-d')
                : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function customer(Actor $cliente, FactusClient $factus, bool $sendEmail): array
    {
        $isJuridica = $cliente->tipo_persona === 'juridica' || (int) $cliente->tipo_documento_id === 6;
        $identification = $this->splitNit((string) $cliente->identificacion);

        if ($sendEmail && ! filter_var((string) $cliente->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El cliente no tiene un correo valido para enviar la factura electronica.');
        }

        return array_filter([
            'identification_document_id' => $this->identificationDocumentId((int) $cliente->tipo_documento_id),
            'identification' => $identification['number'],
            'dv' => $identification['dv'],
            'company' => $isJuridica ? ($cliente->razon_social ?: $cliente->nombre) : null,
            'trade_name' => $isJuridica ? ($cliente->nombre ?: $cliente->razon_social) : null,
            'names' => $isJuridica ? null : ($cliente->nombre ?: $cliente->razon_social),
            'address' => $cliente->direccion ?: 'Sin direccion',
            'email' => $cliente->email ?: 'cliente@example.com',
            'phone' => $cliente->telefono ?: '3000000000',
            'legal_organization_id' => $isJuridica ? 1 : 2,
            'tribute_id' => $cliente->responsable_iva ? 1 : 21,
            'municipality_id' => $this->municipalityId($cliente, $factus),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function items(Factura $factura): array
    {
        return $factura->detalles->map(function ($detalle) use ($factura) {
            $producto = Product::where('empresa_id', $factura->empresa_id)
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

    private function shouldSendEmail(?ConfiguracionEmpresa $configuracion): bool
    {
        if ($configuracion && $configuracion->factus_enabled) {
            return (bool) $configuracion->factus_send_email;
        }

        return filter_var(config('services.factus.send_email'), FILTER_VALIDATE_BOOL);
    }

    private function numberingRangeId(?ConfiguracionEmpresa $configuracion): ?int
    {
        if ($configuracion && $configuracion->factus_enabled) {
            if (! $configuracion->factus_numbering_range_id) {
                throw new \InvalidArgumentException('La empresa no tiene un rango Factus configurado. Sincroniza o selecciona un ID de rango antes de facturar.');
            }

            return (int) $configuracion->factus_numbering_range_id;
        }

        $id = config('services.factus.numbering_range_id');

        return $id ? (int) $id : null;
    }

    private function identificationDocumentId(int $tipoDocumentoId): int
    {
        return match ($tipoDocumentoId) {
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11 => $tipoDocumentoId,
            default => 3,
        };
    }

    private function paymentMethodCode(string $medioPago): string
    {
        return match (strtolower($medioPago)) {
            'transferencia' => '42',
            default => '10',
        };
    }

    private function municipalityId(Actor $cliente, FactusClient $factus): int
    {
        if (! $cliente->ciudad) {
            return 980;
        }

        $name = (string) $cliente->ciudad->nombre;
        $dianCode = str_pad((string) $cliente->ciudad->codigo_dian, 5, '0', STR_PAD_LEFT);

        return Cache::remember("factus.municipality.{$dianCode}", now()->addDays(30), function () use ($name, $dianCode, $factus) {
            $response = $factus->municipalities($name);
            $municipalities = $response['data'] ?? $response;

            if (isset($municipalities['data']) && is_array($municipalities['data'])) {
                $municipalities = $municipalities['data'];
            }

            $matched = collect($municipalities)
                ->first(fn ($municipality) => str_pad((string) ($municipality['code'] ?? ''), 5, '0', STR_PAD_LEFT) === $dianCode)
                ?? collect($municipalities)->first();

            return (int) ($matched['id'] ?? 980);
        });
    }

    private function clientFor(?ConfiguracionEmpresa $configuracion): FactusClient
    {
        if ($configuracion && $configuracion->factus_enabled) {
            return FactusClient::forEmpresa($configuracion);
        }

        return $this->factus;
    }

    private function splitNit(string $identification): array
    {
        $clean = preg_replace('/[^0-9\-]/', '', $identification) ?: '';

        if (str_contains($clean, '-')) {
            [$number, $dv] = array_pad(explode('-', $clean, 2), 2, null);

            return [
                'number' => preg_replace('/\D/', '', $number) ?: $clean,
                'dv' => $dv !== null ? preg_replace('/\D/', '', $dv) : null,
            ];
        }

        return [
            'number' => preg_replace('/\D/', '', $clean) ?: $identification,
            'dv' => null,
        ];
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
