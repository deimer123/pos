<div wire:poll.5000ms style="min-height:calc(100vh - 52px); background:#111827; padding:0 16px 16px;">

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div style="color:white; font-size:22px; font-weight:700;">🍳 Pantalla de Cocina</div>
        <div style="display:flex; align-items:center; gap:10px;">
            {{-- Filtros --}}
            <button wire:click="$set('filtro','pendientes')"
                style="border:none; border-radius:8px; padding:6px 16px; font-size:13px; font-weight:700; cursor:pointer;
                    background:{{ $filtro === 'pendientes' ? '#d97706' : '#374151' }};
                    color:{{ $filtro === 'pendientes' ? 'white' : '#9ca3af' }};">
                🍳 Pendientes
            </button>
            <button wire:click="$set('filtro','entregadas')"
                style="border:none; border-radius:8px; padding:6px 16px; font-size:13px; font-weight:700; cursor:pointer;
                    background:{{ $filtro === 'entregadas' ? '#16a34a' : '#374151' }};
                    color:{{ $filtro === 'entregadas' ? 'white' : '#9ca3af' }};">
                ✅ Entregadas
            </button>
            <div style="color:#6b7280; font-size:11px;">Auto-actualiza · {{ now()->format('H:i:s') }}</div>
        </div>
    </div>

    @if($ordenes->isEmpty())
        <div style="text-align:center; color:#6b7280; padding:60px 0; font-size:16px;">
            {{ $filtro === 'entregadas' ? '📭 Sin comandas entregadas hoy' : '✅ Sin comandas pendientes en cocina' }}
        </div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px;">
            @foreach($ordenes as $orden)
                @php
                    $todosListos = $orden->items->every(fn($i) => $i->estado_cocina === 'listo');
                    $hayPendientes = $orden->items->contains(fn($i) => in_array($i->estado_cocina, ['enviado', 'preparando']));
                @endphp
                <div style="background:#1f2937; border-radius:12px; overflow:hidden;
                    border:2px solid {{ $todosListos ? '#16a34a' : ($orden->estado === 'en_preparacion' ? '#d97706' : '#2563eb') }};">

                    {{-- Header de la comanda --}}
                    <div style="background:{{ $todosListos ? '#14532d' : ($orden->estado === 'en_preparacion' ? '#92400e' : '#1e3a5f') }}; padding:10px 14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                {{-- Número de orden del día --}}
                                @if($orden->numero_cocina_dia)
                                    <div style="color:#fbbf24; font-size:11px; font-weight:700; margin-bottom:2px;">
                                        📋 Orden #{{ $orden->numero_cocina_dia }} del día
                                    </div>
                                @endif
                                <div style="color:white; font-weight:700; font-size:16px;">{{ $orden->mesa->nombre }}</div>
                                <div style="color:#d1d5db; font-size:11px;">Comanda #{{ $orden->id }}</div>
                            </div>
                            <div style="font-size:11px; font-weight:700; padding:4px 10px; border-radius:99px;
                                background:{{ $todosListos ? '#16a34a' : ($orden->estado === 'en_preparacion' ? '#d97706' : '#2563eb') }};
                                color:white; text-align:center;">
                                {{ $todosListos ? '✅ Listo' : ($orden->estado === 'en_preparacion' ? '🍳 Preparando' : '📋 Nueva') }}
                            </div>
                        </div>
                    </div>

                    {{-- Observaciones de cocina --}}
                    @if($orden->observaciones)
                        <div style="background:#1e3a5f; border-left:3px solid #2563eb; margin:8px 14px 0; padding:6px 10px; border-radius:4px;">
                            <div style="color:#93c5fd; font-size:10px; font-weight:700; margin-bottom:2px;">📝 NOTA COCINA:</div>
                            <div style="color:#dbeafe; font-size:12px;">{{ $orden->observaciones }}</div>
                        </div>
                    @endif

                    {{-- Items --}}
                    <div style="padding:10px 14px;">
                        @foreach($orden->items as $item)
                            <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #374151;">
                                <div style="font-size:18px; font-weight:800; color:white; min-width:30px; text-align:center; background:#374151; border-radius:6px; padding:2px 4px;">
                                    {{ (int)$item->cantidad }}x
                                </div>
                                <div style="flex:1;">
                                    <div style="color:{{ $item->estado_cocina === 'listo' ? '#6b7280' : '#f3f4f6' }}; font-size:13px; font-weight:600;
                                        {{ $item->estado_cocina === 'listo' ? 'text-decoration:line-through;' : '' }}">
                                        {{ $item->producto->descripcion_larga ?? $item->producto->nombre ?? '—' }}
                                    </div>
                                    @if($item->nota_cocina)
                                        <div style="color:#fbbf24; font-size:10px; margin-top:2px;">📝 {{ $item->nota_cocina }}</div>
                                    @endif
                                </div>
                                <div>
                                    @if($item->estado_cocina === 'listo')
                                        <span style="background:#14532d; color:#4ade80; border:1px solid #16a34a; border-radius:8px; padding:5px 10px; font-size:11px; font-weight:700;">
                                            ✓ Listo
                                        </span>
                                    @else
                                        <button wire:click="marcarListo({{ $item->id }})"
                                                style="background:#16a34a; color:white; border:2px solid #22c55e; border-radius:8px; padding:7px 12px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;">
                                            ✅ Marcar Listo
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Footer: tiempo + botón Entregado --}}
                    <div style="padding:8px 14px 12px; display:flex; justify-content:space-between; align-items:center;">
                        <div style="color:#6b7280; font-size:10px;">
                            ⏱ {{ $orden->abierta_en?->diffForHumans() ?? '—' }}
                        </div>
                        @if($todosListos && $filtro === 'pendientes')
                            <button wire:click="marcarEntregada({{ $orden->id }})"
                                    style="background:#2563eb; color:white; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer;">
                                🛎️ Marcar Entregado
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
