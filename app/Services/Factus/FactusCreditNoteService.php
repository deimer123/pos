<?php

namespace App\Services\Factus;

use App\Models\Devolucion;
use Carbon\Carbon;

class FactusCreditNoteService
{
    public function __construct(
        private readonly FactusClient $client,
        private readonly FactusCreditNotePayloadBuilder $payloadBuilder,
    ) {
    }

    public function payload(Devolucion $devolucion): array
    {
        return $this->payloadBuilder->build($devolucion);
    }

    public function validate(Devolucion $devolucion): array
    {
        $payload = $this->payload($devolucion);
        $devolucion->loadMissing('factura.configuracionEmpresa');

        $configuracion = $devolucion->factura?->configuracionEmpresa;
        $client = $configuracion?->factus_enabled
            ? FactusClient::forEmpresa($configuracion)
            : $this->client;

        $response = $client->validateCreditNote($payload);
        $data = $response['data'] ?? [];
        $creditNote = $data['credit_note'] ?? $data['note'] ?? $data;
        $validated = (bool) ($creditNote['is_validated'] ?? false)
            || (int) ($creditNote['status'] ?? 0) === 1
            || filled($creditNote['validated'] ?? null)
            || filled($creditNote['validated_at'] ?? null)
            || filled($creditNote['cude'] ?? null);

        $devolucion->update([
            'factus_credit_note_reference_code' => $creditNote['reference_code'] ?? $payload['reference_code'],
            'factus_credit_note_id' => $creditNote['id'] ?? $creditNote['credit_note_id'] ?? null,
            'factus_credit_note_number' => $creditNote['number'] ?? null,
            'factus_credit_note_cude' => $creditNote['cude'] ?? null,
            'factus_credit_note_status' => $validated ? 'validada' : 'pendiente',
            'factus_credit_note_response' => $response,
            'factus_credit_note_validated_at' => isset($creditNote['validated_at']) || isset($creditNote['validated'])
                ? $this->parseFactusDate($creditNote['validated_at'] ?? $creditNote['validated'])
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
