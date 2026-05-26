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
        $payload = $this->payload($factura);
        $response = $this->client->validateBill($payload);
        $data = $response['data'] ?? [];

        $factura->update([
            'factus_reference_code' => $payload['reference_code'],
            'factus_bill_id' => $data['id'] ?? $data['bill_id'] ?? null,
            'factus_number' => $data['number'] ?? null,
            'factus_cufe' => $data['cufe'] ?? null,
            'factus_status' => ($data['is_validated'] ?? false) ? 'validada' : 'pendiente',
            'factus_response' => $response,
            'factus_validated_at' => isset($data['validated_at'])
                ? $this->parseFactusDate($data['validated_at'])
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
