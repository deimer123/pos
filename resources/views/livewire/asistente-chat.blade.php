<div
    @if(! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) style="display:none" @endif
    id="asistente-chat-widget"
>
    <style>
        #asistente-chat-widget {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        #asistente-chat-widget .ac-toggle {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: #4f46e5;
            color: #fff;
            font-size: 24px;
            border: none;
            box-shadow: 0 6px 18px rgba(0,0,0,.25);
            cursor: pointer;
            line-height: 56px;
            text-align: center;
        }
        #asistente-chat-widget .ac-panel {
            width: 340px;
            max-width: 90vw;
            height: 440px;
            max-height: 70vh;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #asistente-chat-widget .ac-header {
            background: #4f46e5;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 600;
        }
        #asistente-chat-widget .ac-header button {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        #asistente-chat-widget .ac-mensajes {
            flex: 1;
            overflow-y: auto;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        #asistente-chat-widget .ac-bubble {
            max-width: 85%;
            padding: 8px 12px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-line;
        }
        #asistente-chat-widget .ac-bubble.user {
            align-self: flex-end;
            background: #4f46e5;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        #asistente-chat-widget .ac-bubble.assistant {
            align-self: flex-start;
            background: #f1f2f6;
            color: #222;
            border-bottom-left-radius: 4px;
        }
        #asistente-chat-widget .ac-form {
            display: flex;
            gap: 8px;
            padding: 10px;
            border-top: 1px solid #eee;
        }
        #asistente-chat-widget .ac-form input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 13px;
            outline: none;
        }
        #asistente-chat-widget .ac-form button {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: #4f46e5;
            color: #fff;
            border: none;
            cursor: pointer;
            flex-shrink: 0;
        }
        #asistente-chat-widget .ac-form button:disabled {
            opacity: .5;
        }
    </style>

    @if($abierto)
        <div class="ac-panel">
            <div class="ac-header">
                <span>🤖 Asistente de tu negocio</span>
                <button type="button" wire:click="alternar" aria-label="Cerrar">✕</button>
            </div>

            <div class="ac-mensajes" id="asistente-mensajes">
                @foreach($mensajes as $mensaje)
                    <div class="ac-bubble {{ $mensaje['role'] === 'user' ? 'user' : 'assistant' }}">
                        {{ $mensaje['content'] }}
                    </div>
                @endforeach

                <div wire:loading wire:target="enviar" class="ac-bubble assistant">
                    Consultando…
                </div>
            </div>

            <form wire:submit="enviar" class="ac-form">
                <input
                    type="text"
                    wire:model="pregunta"
                    placeholder="Ej: ¿cuánto vendí hoy?"
                    autocomplete="off"
                >
                <button type="submit" wire:loading.attr="disabled" wire:target="enviar" aria-label="Enviar">➤</button>
            </form>
        </div>
    @endif

    <button type="button" class="ac-toggle" wire:click="alternar" aria-label="Abrir asistente">
        {{ $abierto ? '✕' : '💬' }}
    </button>
</div>
