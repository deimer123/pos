<x-filament::page>
    <x-filament::section heading="Emitir código de activación" class="combo-franja-azul">
        <form wire:submit.prevent="emitirCodigo" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary" icon="heroicon-o-key">
                Generar código de activación
            </x-filament::button>
        </form>

        @if($codigoGenerado)
            <div style="margin-top:20px; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:10px; padding:20px;">
                <div style="font-size:13px; color:#374151; margin-bottom:8px;">Código de activación (pegarlo tal cual en la pantalla de activación del cliente):</div>
                <textarea readonly rows="4" onclick="this.select()"
                    style="width:100%; font-family:monospace; font-size:13px; padding:10px; border:1px solid #d1d5db; border-radius:8px; resize:vertical;"
                >{{ $codigoGenerado }}</textarea>
            </div>
        @endif
    </x-filament::section>

    @php $licencias = $this->licenciasEmitidas(); @endphp

    <div style="margin-top:24px;">
        <x-filament::section heading="Últimas licencias emitidas" class="combo-franja-azul">
            @if($licencias->isEmpty())
                <p style="font-size:13px; color:#6b7280;">Todavía no se emitió ninguna licencia Local.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:13px; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                                <th style="padding:8px 16px 8px 0;">Empresa</th>
                                <th style="padding:8px 16px 8px 0;">Máquina</th>
                                <th style="padding:8px 16px 8px 0;">Rol</th>
                                <th style="padding:8px 16px 8px 0;">Emitida</th>
                                <th style="padding:8px 16px 8px 0;">Emitida por</th>
                                <th style="padding:8px 16px 8px 0;">Estado</th>
                                <th style="padding:8px 16px 8px 0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($licencias as $licencia)
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 16px 8px 0; font-weight:600;">{{ $licencia->empresa?->name ?? "#{$licencia->empresa_id}" }}</td>
                                    <td style="padding:8px 16px 8px 0;">
                                        {{ $licencia->machine_label ?: '—' }}
                                        <div style="font-size:11px; color:#6b7280; font-family:monospace;">{{ $licencia->machine_id }}</div>
                                    </td>
                                    <td style="padding:8px 16px 8px 0;">
                                        <span style="padding:2px 8px; border-radius:5px; background:{{ $licencia->rol === 'cliente' ? '#3b82f6' : '#8b5cf6' }}; color:#fff; font-size:11px; font-weight:600;">
                                            {{ $licencia->rol === 'cliente' ? 'Terminal' : 'Servidor' }}
                                        </span>
                                    </td>
                                    <td style="padding:8px 16px 8px 0;">{{ $licencia->issued_at->format('d/m/Y H:i') }}</td>
                                    <td style="padding:8px 16px 8px 0;">{{ $licencia->creador?->name ?? '—' }}</td>
                                    <td style="padding:8px 16px 8px 0;">
                                        @if($licencia->revocado_at)
                                            <span style="padding:2px 8px; border-radius:5px; background:#ef4444; color:#fff; font-size:11px; font-weight:600;">Revocada</span>
                                        @else
                                            <span style="padding:2px 8px; border-radius:5px; background:#22c55e; color:#fff; font-size:11px; font-weight:600;">Activa</span>
                                        @endif
                                    </td>
                                    <td style="padding:8px 16px 8px 0; text-align:right;">
                                        <button type="button"
                                            data-codigo="{{ $licencia->codigo_emitido }}"
                                            onclick="navigator.clipboard.writeText(this.dataset.codigo); const t=this.textContent; this.textContent='Copiado ✓'; setTimeout(() => this.textContent=t, 1500);"
                                            style="padding:4px 10px; border-radius:6px; border:1px solid #6366f1; background:#fff; color:#6366f1; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">
                                            Copiar código
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
