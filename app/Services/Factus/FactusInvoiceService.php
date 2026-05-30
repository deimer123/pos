<?php

namespace App\Services\Factus;

use App\Models\Factura;
use Carbon\Carbon;

class FactusInvoiceService
{
    public function __construct(
        private readonly FactusClient $client,
        private readonly FactusInvoicePayloadBuilder $payloadBuilder,
    ) {
    }

    public function payload(Factura $factura): array
    {
        return $this->payloadBuilder->build($factura);
    }

    public function validate(Factura $factura): array
    {
        if (! config('services.factus.enabled', false)) {
            $response = [
                'skipped' => true,
                'message' => 'Factus validation is disabled. The bill was saved as salida.',
            ];

            $factura->update([
                'tipo_factura' => 'salida',
                'factus_reference_code' => null,
                'factus_bill_id' => null,
                'factus_number' => 'NO_APLICA',
                'factus_cufe' => 'NO_APLICA',
                'factus_status' => 'validada',
                'factus_response' => $response,
                'factus_validated_at' => now(),
            ]);

            return $response;
        }

        $payload = $this->payload($factura);
        $response = $this->client->validateBill($payload);
        $data = $response['data'] ?? [];
        $bill = $data['bill'] ?? $data;
        $validated = (bool) ($bill['is_validated'] ?? false)
            || (int) ($bill['status'] ?? 0) === 1
            || filled($bill['validated'] ?? null);

        $factura->update([
            'factus_reference_code' => $bill['reference_code'] ?? $payload['reference_code'],
            'factus_bill_id' => $bill['id'] ?? $bill['bill_id'] ?? null,
            'factus_number' => $bill['number'] ?? null,
            'factus_cufe' => $bill['cufe'] ?? null,
            'factus_status' => $validated ? 'validada' : 'pendiente',
            'factus_response' => $response,
            'factus_validated_at' => isset($bill['validated_at']) || isset($bill['validated'])
                ? $this->parseFactusDate($bill['validated_at'] ?? $bill['validated'])
                : now(),
        ]);

        return $response;
    }

    private function parseFactusDate(string $date): ?Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
