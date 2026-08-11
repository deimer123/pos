<?php

namespace App\Services\Ubl21;

use App\Models\Devolucion;

class Ubl21CreditNoteService
{
    public function __construct(
        private readonly Ubl21CreditNotePayloadBuilder $payloadBuilder,
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

        $configuracion = $devolucion->factura->configuracionEmpresa;

        if (! $configuracion) {
            throw new \RuntimeException('La empresa no tiene configuracion.');
        }

        $client = Ubl21Client::forEmpresa($configuracion);
        $response = $client->emitirNotaCredito($payload);

        $isValid = (bool) ($response['is_valid'] ?? $response['success'] ?? false);

        $devolucion->update([
            'ubl21_credit_note_number' => $this->numeroDesdeMensaje((string) ($response['message'] ?? '')),
            'ubl21_credit_note_cufe' => $response['cufe'] ?? null,
            'ubl21_credit_note_qr' => $response['QRStr'] ?? null,
            'ubl21_credit_note_status' => $isValid ? 'validada' : 'pendiente',
            'ubl21_credit_note_response' => $response,
            'ubl21_credit_note_pdf_url' => $response['urlinvoicepdf'] ?? null,
            'ubl21_credit_note_xml_url' => $response['urlinvoicexml'] ?? null,
            'ubl21_credit_note_validated_at' => now(),
        ]);

        return $response;
    }

    private function numeroDesdeMensaje(string $mensaje): ?string
    {
        return preg_match('/#([A-Za-z0-9]+)/', $mensaje, $match) ? $match[1] : null;
    }
}
