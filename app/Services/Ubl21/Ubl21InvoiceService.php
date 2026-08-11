<?php

namespace App\Services\Ubl21;

use App\Models\Factura;

class Ubl21InvoiceService
{
    public function __construct(
        private readonly Ubl21InvoicePayloadBuilder $payloadBuilder,
    ) {
    }

    public function payload(Factura $factura): array
    {
        return $this->payloadBuilder->build($factura);
    }

    public function validate(Factura $factura): array
    {
        $payload = $this->payload($factura);
        $factura->loadMissing('configuracionEmpresa');

        $configuracion = $factura->configuracionEmpresa;

        if (! $configuracion) {
            throw new \RuntimeException('La empresa no tiene configuracion.');
        }

        $client = Ubl21Client::forEmpresa($configuracion);
        $response = $client->emitirFactura($payload);

        $isValid = (bool) ($response['is_valid'] ?? $response['success'] ?? false);

        $factura->update([
            'ubl21_document_number' => $this->numeroDesdeMensaje((string) ($response['message'] ?? '')),
            'ubl21_cufe' => $response['cufe'] ?? null,
            'ubl21_qr' => $response['QRStr'] ?? null,
            'ubl21_status' => $isValid ? 'validada' : 'pendiente',
            'ubl21_response' => $response,
            'ubl21_pdf_url' => $response['urlinvoicepdf'] ?? null,
            'ubl21_xml_url' => $response['urlinvoicexml'] ?? null,
            'ubl21_validated_at' => now(),
        ]);

        return $response;
    }

    // El proveedor no devuelve el numero de documento en un campo aparte
    // del DocumentResponse -- viene incrustado en "message" (ej:
    // "Documento #SETP990000001 generado..."), confirmado probando la API
    // real.
    private function numeroDesdeMensaje(string $mensaje): ?string
    {
        return preg_match('/#([A-Za-z0-9]+)/', $mensaje, $match) ? $match[1] : null;
    }
}
