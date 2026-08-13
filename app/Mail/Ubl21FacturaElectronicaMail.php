<?php

namespace App\Mail;

use App\Models\Factura;
use App\Support\FacturaImpresionData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
     * El PDF que entrega el proveedor alterno (urlinvoicepdf/regeneratepdf)
     * viene con huecos que no controlamos (logo, telefono/correo de la
     * empresa mal registrados en su onboarding, ciudad del cliente, rango y
     * resolucion no se ven) -- en vez de depender de esa plantilla, se
     * genera el PDF con la misma vista que ya usa el ticket impreso
     * (facturas.imprimir-carta), que si tiene todos los datos correctos.
     *
     * El XML si se adjunta tal cual lo entrega el proveedor (viene en
     * base64 en la misma respuesta de la validacion, ya guardada en
     * ubl21_response -- no hace falta pedirlo aparte) -- es el documento
     * UBL 2.1 firmado, igual al que adjunta Factus en su propio correo.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        try {
            $data = ['factura' => $this->factura, ...FacturaImpresionData::calcular($this->factura)];

            // dompdf tiene enable_remote=false (config/dompdf.php) por
            // seguridad -- no descarga imagenes por URL, asi que el logo
            // (que FacturaImpresionData entrega como URL publica, pensado
            // para el navegador) hay que pasarlo como data URI para que se
            // vea en este PDF generado en servidor.
            $logo = $data['config']?->logo;
            if ($logo && Storage::disk('public')->exists($logo)) {
                $mime = Storage::disk('public')->mimeType($logo) ?: 'image/png';
                $data['logoUrl'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($logo));
            }

            $pdf = Pdf::loadView('facturas.imprimir-carta', $data)->setPaper('letter');

            $attachments[] = Attachment::fromData(fn () => $pdf->output(), "{$this->factura->numero_visual}.pdf")
                ->withMime('application/pdf');
        } catch (\Throwable) {
            //
        }

        $xmlBase64 = $this->factura->ubl21_response['invoicexml'] ?? null;

        if (filled($xmlBase64)) {
            $attachments[] = Attachment::fromData(fn () => base64_decode($xmlBase64), "{$this->factura->numero_visual}.xml")
                ->withMime('application/xml');
        }

        return $attachments;
    }
}
