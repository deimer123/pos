<div
    @if(! auth()->check() || ! auth()->user()->hasRole('admin_empresa')) style="display:none" @endif
    class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
    x-data
>
    @if($abierto)
        <div
            class="flex h-[28rem] w-80 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:w-96"
        >
            <div class="flex items-center justify-between border-b border-gray-200 bg-indigo-600 px-4 py-3 dark:border-gray-700">
                <div class="flex items-center gap-2 text-white">
                    <span class="text-lg">🤖</span>
                    <span class="text-sm font-semibold">Asistente de tu negocio</span>
                </div>
                <button
                    type="button"
                    wire:click="alternar"
                    class="rounded-full p-1 text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="Cerrar asistente"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div
                id="asistente-mensajes"
                x-ref="mensajes"
                x-effect="$refs.mensajes && ($refs.mensajes.scrollTop = $refs.mensajes.scrollHeight)"
                class="flex-1 space-y-3 overflow-y-auto px-4 py-4"
            >
                @foreach($mensajes as $mensaje)
                    <div class="flex {{ $mensaje['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div
                            class="max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2 text-sm
                            {{ $mensaje['role'] === 'user'
                                ? 'rounded-br-sm bg-indigo-600 text-white'
                                : 'rounded-bl-sm bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100' }}"
                        >
                            {{ $mensaje['content'] }}
                        </div>
                    </div>
                @endforeach

                <div wire:loading wire:target="enviar" class="flex justify-start">
                    <div class="rounded-2xl rounded-bl-sm bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        Consultando…
                    </div>
                </div>
            </div>

            <form wire:submit="enviar" class="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                <input
                    type="text"
                    wire:model="pregunta"
                    placeholder="Ej: ¿cuánto vendí hoy?"
                    autocomplete="off"
                    class="flex-1 rounded-full border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="enviar"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    aria-label="Enviar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3.105 3.105a.75.75 0 01.815-.163l13.5 5.25a.75.75 0 010 1.416l-13.5 5.25a.75.75 0 01-.977-.978l1.581-4.741a.75.75 0 010-.474L3.105 4.083a.75.75 0 010-.978z" />
                    </svg>
                </button>
            </form>
        </div>
    @endif

    <button
        type="button"
        wire:click="alternar"
        class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-2xl text-white shadow-lg transition hover:bg-indigo-700"
        aria-label="Abrir asistente"
    >
        {{ $abierto ? '✕' : '💬' }}
    </button>
</div>
