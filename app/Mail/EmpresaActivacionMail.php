<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de activacion que se le manda a la empresa cuando el super_admin
 * la crea desde "Crear Empresa" -- lleva el login (correo + contraseña en
 * texto plano, tal como la escribio el super_admin en el formulario, ya
 * que en el registro solo queda el hash) y la descripcion del plan
 * comercial elegido. Se dispara desde CreateEmpresa::afterCreate().
 */
class EmpresaActivacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $empresa,
        public string $password,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu empresa {$this->empresa->name} ya está activa",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.empresa-activacion',
            with: [
                'empresa' => $this->empresa,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl,
                'plan' => $this->empresa->plan,
            ],
        );
    }
}
