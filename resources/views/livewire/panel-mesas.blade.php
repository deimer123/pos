<div class="panel-mesas" wire:poll.8000ms style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">


    {{-- Header --}}
    <div style="padding:10px 16px; background:#4338ca; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <span style="font-size:16px; font-weight:700;">🪑 Mesas</span>
            @if($zonas->isNotEmpty())
            <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <button wire:click="$set('zonaFiltro','')"
                    style="border:none; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; cursor:pointer;
                        background:{{ $zonaFiltro === '' ? 'white' : 'rgba(255,255,255,.2)' }};
                        color:{{ $zonaFiltro === '' ? '#4338ca' : 'white' }};">
                    Todas
                </button>
                @foreach($zonas as $zona)
                    <button wire:click="$set('zonaFiltro','{{ $zona }}')"
                        style="border:none; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; cursor:pointer;
                            background:{{ $zonaFiltro === $zona ? 'white' : 'rgba(255,255,255,.2)' }};
                            color:{{ $zonaFiltro === $zona ? '#4338ca' : 'white' }};">
                        {{ $zona }}
                    </button>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Leyenda --}}
    <div style="padding:8px 16px; background:white; border-bottom:1px solid #e2e8f0; display:flex; gap:16px; flex-shrink:0;">
        <span style="font-size:11px; color:#64748b; display:flex; align-items:center; gap:4px;">
            <span style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:inline-block;"></span> Libre
        </span>
        <span style="font-size:11px; color:#64748b; display:flex; align-items:center; gap:4px;">
            <span style="width:10px;height:10px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Ocupada
        </span>
        <span style="font-size:11px; color:#64748b; display:flex; align-items:center; gap:4px;">
            <span style="width:10px;height:10px;background:#f59e0b;border-radius:50%;display:inline-block;"></span> Cuenta en espera (ver arriba)
        </span>
    </div>

    {{-- Cuentas pendientes: ocultas para mesero --}}
    @php $esMesero = auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor']); @endphp
    @if($cuentasPendientes->isNotEmpty() && ! $esMesero)
    <div style="padding:10px 16px; background:#fffbeb; border-bottom:2px solid #fbbf24; flex-shrink:0;">
        <div style="font-size:12px; font-weight:700; color:#92400e; margin-bottom:8px;">
            ⏸ Cuentas en espera ({{ $cuentasPendientes->count() }})
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            @foreach($cuentasPendientes as $cp)
            <div style="background:white; border:2px solid #fbbf24; border-radius:10px; padding:8px 12px; display:flex; align-items:center; gap:10px; min-width:180px;">
                <div>
                    <div style="font-size:12px; font-weight:700; color:#78350f;">
                        🪑 {{ $cp->mesa->nombre ?? 'Mesa' }}
                        @if($cp->mesa->zona ?? null)
                            <span style="font-weight:400; font-size:10px;">({{ $cp->mesa->zona }})</span>
                        @endif
                    </div>
                    <div style="font-size:13px; font-weight:800; color:#b45309; margin-top:2px;">
                        ${{ number_format($cp->total, 0, ',', '.') }}
                    </div>
                </div>
                <button wire:click="cobrarCuentaPendiente({{ $cp->id }})"
                    style="background:#16a34a; color:white; border:none; border-radius:8px; padding:5px 10px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;">
                    💳 Cobrar
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Grid de mesas --}}
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @if($mesas->isEmpty())
            <div style="text-align:center; padding:40px; color:#94a3b8;">
                <div style="font-size:40px;">🪑</div>
                <div style="margin-top:8px;">No hay mesas configuradas.<br>Créelas en <strong>Administración → Mesas</strong>.</div>
            </div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:12px;">
                @foreach($mesas as $mesa)
                    @php
                        $ordenActiva = $mesa->ordenes->first();
                        $estado = $mesa->estado;

                        // Detectar si todos los items enviados a cocina están listos
                        $listoParaEntregar = false;
                        if ($ordenActiva) {
                            $itemsCocina = $ordenActiva->items->whereIn('estado_cocina', ['enviado', 'preparando', 'listo']);
                            $listoParaEntregar = $itemsCocina->isNotEmpty()
                                && $itemsCocina->every(fn($i) => $i->estado_cocina === 'listo')
                                && ! $ordenActiva->entregada;
                        }

                        // Color basado en si hay orden activa, no en mesa.estado
                        $tieneCuentaEnEspera = $cuentasPendientes->where('mesa_id', $mesa->id)->isNotEmpty();
                        if ($listoParaEntregar) {
                            $color = '#16a34a'; $textColor = '#14532d'; $bg = '#dcfce7'; $border = '#16a34a';
                        } elseif ($ordenActiva) {
                            $color = '#ef4444'; $textColor = '#7f1d1d'; $bg = '#fee2e2'; $border = '#fca5a5';
                        } elseif ($tieneCuentaEnEspera) {
                            $color = '#f59e0b'; $textColor = '#78350f'; $bg = '#fffbeb'; $border = '#fbbf24';
                        } else {
                            $color = '#22c55e'; $textColor = '#14532d'; $bg = '#dcfce7'; $border = '#86efac';
                        }
                    @endphp
                    <div wire:click="abrirMesa({{ $mesa->id }})"
                         style="background:{{ $bg }}; border:2px solid {{ $border }}; border-radius:12px; padding:14px 10px; text-align:center; cursor:pointer; transition:transform .15s; user-select:none;
                            {{ $listoParaEntregar ? 'box-shadow:0 0 0 3px #16a34a, 0 0 12px #16a34a66;' : '' }}"
                         onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                        <div style="font-size:22px; margin-bottom:4px;">{{ $listoParaEntregar ? '🍽️' : '🪑' }}</div>
                        <div style="font-size:13px; font-weight:700; color:{{ $textColor }};">{{ $mesa->nombre }}</div>
                        <div style="font-size:10px; color:{{ $textColor }}; opacity:.7; margin-top:2px;">{{ $mesa->codigo }}</div>
                        @if($mesa->capacidad)
                            <div style="font-size:10px; color:{{ $textColor }}; opacity:.6;">👥 {{ $mesa->capacidad }} pax</div>
                        @endif
                        @if($listoParaEntregar)
                            <div style="margin-top:6px; background:#16a34a; color:white; border-radius:99px; padding:2px 8px; font-size:10px; font-weight:700;">
                                🍽️ Listo para entregar
                            </div>
                        @elseif($ordenActiva)
                            <div style="margin-top:6px; background:{{ $color }}; color:white; border-radius:99px; padding:2px 8px; font-size:10px; font-weight:600;">
                                ${{ number_format($ordenActiva->total, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('warning', (msg) => {
            const text = Array.isArray(msg) ? msg[0] : msg;
            Swal.fire({ icon: 'warning', title: '⚠️ Atención', text: text, confirmButtonColor: '#f59e0b' });
        });
    });
</script>
