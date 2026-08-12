<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * El proveedor alterno de facturacion electronica (UBL 2.1) no envia el
 * correo al cliente por su cuenta (a diferencia de Factus, que si lo hace
 * cuando factus_send_email esta activo) -- este mail lo suple desde el
 * propio servidor. Se dispara desde Ubl21InvoiceService::validate().
 */
class Ubl21FacturaElectronicaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Factura $factura)
    {
    }

    public function envelope(): Envelope
    {
        $configuracion = $this->factura->configuracionEmpresa;
        $empresa = $configuracion?->nombre_empresa ?: 'Facturación electrónica';

        // Cada empresa puede tener su propio remitente verificado (ej. en
        // Brevo); si no lo configuro, cae al remitente global del .env
        // (MAIL_FROM_ADDRESS/MAIL_FROM_NAME) via Envelope::from = null.
        $from = filled($configuracion?->ubl21_mail_from_address)
            ? new Address($configuracion->ubl21_mail_from_address, $configuracion->ubl21_mail_from_name ?: $empresa)
            : null;

        return new Envelope(
            from: $from,
            subject: "Factura electrónica {$this->factura->numero_visual} - {$empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura-electronica',
            with: [
                'factura' => $this->factura,
                'empresa' => $this->factura->configuracionEmpresa,
                'cliente' => $this->factura->cliente,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $urlPdf = $this->factura->ubl21_pdf_url;

        if (blank($urlPdf)) {
            return [];
        }

        try {
            $response = Http::timeout(20)->get($urlPdf);

            if ($response->failed()) {
                return [];
            }

            return [
                Attachment::fromData(fn () => $response->body(), "{$this->factura->numero_visual}.pdf")
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
