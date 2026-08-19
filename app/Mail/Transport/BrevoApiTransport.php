<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Envia correo por la API HTTP de Brevo (https://api.brevo.com/v3/smtp/email,
 * puerto 443) en vez de por SMTP (smtp-relay.brevo.com:587) -- desde el
 * droplet la conexion SMTP se queda en timeout (puerto saliente 587
 * bloqueado por el firewall del proveedor), pero HTTPS si funciona porque
 * es el mismo puerto que usa el resto del trafico saliente del sistema
 * (Factus, UBL21, etc).
 *
 * Se registra en AppServiceProvider::boot() via Mail::extend('brevo', ...)
 * y se activa poniendo MAIL_MAILER=brevo en el .env (junto con
 * BREVO_API_KEY). Mientras tanto MAIL_MAILER=smtp sigue funcionando igual
 * que antes para quien no tenga el problema de firewall.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('BrevoApiTransport solo soporta mensajes Symfony\Component\Mime\Email.');
        }

        $remitente = ($email->getFrom()[0] ?? null) ?? $email->getSender();

        if (! $remitente instanceof Address) {
            throw new TransportException('El correo no tiene remitente (from).');
        }

        $payload = array_filter([
            'sender' => $this->direccion($remitente),
            'to' => $this->direcciones($email->getTo()),
            'cc' => $this->direcciones($email->getCc()) ?: null,
            'bcc' => $this->direcciones($email->getBcc()) ?: null,
            'replyTo' => ($replyTo = $email->getReplyTo()[0] ?? null) ? $this->direccion($replyTo) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ?: null,
            'textContent' => $email->getTextBody() ?: null,
            'attachment' => $this->adjuntos($email) ?: null,
        ]);

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException("Brevo API respondio {$response->status()}: {$response->body()}");
        }
    }

    private function direccion(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]);
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array<string, string>>
     */
    private function direcciones(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->direccion($address), $addresses);
    }

    /**
     * @return array<int, array{name: string, content: string}>
     */
    private function adjuntos(Email $email): array
    {
        return array_values(array_map(
            fn (DataPart $part) => [
                'name' => $part->getFilename() ?: 'adjunto',
                'content' => base64_encode($part->getBody()),
            ],
            $email->getAttachments(),
        ));
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
