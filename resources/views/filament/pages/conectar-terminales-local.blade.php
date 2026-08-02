<x-filament::page>
    <x-filament::section heading="Conectar un terminal nuevo" class="combo-franja-azul">
        @php $url = $this->urlParaTerminales(); @endphp

        @if($url)
            <p style="font-size:13px; color:#6b7280; margin-bottom:12px;">
                En el equipo nuevo, elegí "Terminal / cliente" y escribí esta dirección cuando la pida. Ese equipo tiene que estar en la misma red local que este servidor.
            </p>
            <div style="border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; padding:16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <code style="font-family:monospace; font-size:14px; color:#166534;" id="url-terminal-local">{{ $url }}</code>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('url-terminal-local').textContent)"
                    style="padding:.4rem .8rem; border-radius:6px; border:1px solid #16a34a; background:#fff; color:#16a34a; font-size:.8rem; cursor:pointer;">
                    Copiar
                </button>
            </div>
        @else
            <div style="border:1px solid #fde68a; background:#fffbeb; border-radius:10px; padding:16px;">
                <p style="font-size:13px; color:#92400e; margin:0;">
                    No se pudo detectar la dirección de red de este equipo todavía. Verificá que esté conectado a la red local (cable o wifi) y reiniciá la app.
                </p>
            </div>
        @endif

        <p style="font-size:12px; color:#9ca3af; margin-top:16px;">
            Cada terminal necesita su propio código de activación (emitido para este equipo específico) -- no alcanza con la dirección sola.
        </p>
    </x-filament::section>

    <div style="margin-top:24px;">
        <x-filament::section heading="Terminales conectados" class="combo-franja-azul">
            @php $terminales = $this->terminales(); @endphp

            @if($terminales->isEmpty())
                <p style="font-size:13px; color:#6b7280;">Todavía no se conectó ningún terminal a este servidor.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:13px; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                                <th style="padding:8px 16px 8px 0;">Máquina</th>
                                <th style="padding:8px 16px 8px 0;">Conectado</th>
                                <th style="padding:8px 16px 8px 0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($terminales as $terminal)
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 16px 8px 0; font-family:monospace;">{{ $terminal->machine_id }}</td>
                                    <td style="padding:8px 16px 8px 0;">{{ $terminal->activada_at->format('d/m/Y H:i') }}</td>
                                    <td style="padding:8px 16px 8px 0; text-align:right;">
                                        <button
                                            type="button"
                                            wire:click="revocarTerminal({{ $terminal->id }})"
                                            wire:confirm="¿Desconectar este terminal? Va a tener que volver a pedir el código de activación si se quiere reconectar."
                                            style="padding:.35rem .8rem; border-radius:6px; border:1px solid #dc2626; background:#fff; color:#dc2626; font-size:.8rem; cursor:pointer;"
                                        >
                                            Desconectar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament::page>
