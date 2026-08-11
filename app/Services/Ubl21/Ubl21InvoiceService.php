<?php

namespace App\Services\Ubl21;

use App\Mail\Ubl21FacturaElectronicaMail;
use App\Models\Factura;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        if ($isValid) {
            $this->enviarCorreoCliente($factura);
        }

        return $response;
    }

    // El proveedor no envia el correo al cliente por su cuenta (a
    // diferencia de Factus, que si lo hace cuando factus_send_email esta
    // activo) -- lo mandamos nosotros. Nunca debe tumbar la venta: un
    // fallo de correo se registra y ya, la factura ya quedo validada en la
    // DIAN.
    private function enviarCorreoCliente(Factura $factura): void
    {
        $factura->loadMissing('cliente');
        $cliente = $factura->cliente;

        if (! $cliente || blank($cliente->email) || $cliente->nombre === 'CONSUMIDOR FINAL') {
            return;
        }

        try {
            Mail::to($cliente->email)->send(new Ubl21FacturaElectronicaMail($factura));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo de factura electronica (proveedor alterno)', [
                'factura_id' => $factura->id,
                'error' => $e->getMessage(),
            ]);
        }
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
