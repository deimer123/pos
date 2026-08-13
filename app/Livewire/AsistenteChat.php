<?php

namespace App\Livewire;

use App\Services\Asistente\AsistenteClaudeService;
use Livewire\Component;

/**
 * Burbuja de chat flotante del asistente de IA, visible solo para
 * admin_empresa (gateado en el render hook de AdminPanelProvider, y de
 * nuevo aqui por si el componente se usa en otro lado). Cada pregunta se
 * responde solo con datos de la empresa del usuario logueado -- ver
 * App\Services\Asistente\AsistenteClaudeService.
 */
class AsistenteChat extends Component
{
    public bool $abierto = false;

    public string $pregunta = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $mensajes = [];

    public function mount(): void
    {
        if (! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) {
            $this->abierto = false;

            return;
        }

        $this->mensajes[] = [
            'role' => 'assistant',
            'content' => '¡Hola! Pregúntame algo sobre tu negocio: ventas, productos, cartera o clientes.',
        ];
    }

    public function alternar(): void
    {
        $this->abierto = ! $this->abierto;
    }

    public function enviar(AsistenteClaudeService $asistente): void
    {
        $pregunta = trim($this->pregunta);

        if ($pregunta === '' || ! auth()->user()?->hasRole('admin_empresa')) {
            return;
        }

        $this->mensajes[] = ['role' => 'user', 'content' => $pregunta];
        $this->pregunta = '';

        $respuesta = $asistente->preguntar(auth()->user(), $this->mensajes, $pregunta);

        $this->mensajes[] = ['role' => 'assistant', 'content' => $respuesta];
    }

    public function render()
    {
        return view('livewire.asistente-chat');
    }
}
