<div class="panel-mesas" wire:poll.8000ms style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div style="padding:10px 16px; background:#4338ca; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:16px; font-weight:700;">🪑 Mesas</span>
                @if($usaDomicilios)
                <button wire:click="$toggle('mostrarDomicilios')"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                        background:{{ $mostrarDomicilios ? '#f97316' : 'rgba(255,255,255,.2)' }};
                        color:white; display:flex; align-items:center; gap:5px;">
                    🛵 Domicilios
                    @if(!$mostrarDomicilios)
                        @php $pendientesCount = \App\Models\OrdenMesa::where('empresa_id', auth()->user()->getEmpresaActualId())->where('tipo_pedido','domicilio')->whereIn('estado',['abierta','en_preparacion'])->whereDate('created_at', today())->count(); @endphp
                        @if($pendientesCount > 0)
                            <span style="background:#ef4444; border-radius:99px; padding:1px 6px; font-size:10px;">{{ $pendientesCount }}</span>
                        @endif
                    @endif
                </button>
                <button wire:click="nuevoDomicilio"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                        background:#16a34a; color:white; display:flex; align-items:center; gap:5px;">
                    ➕ Nuevo Domicilio
                </button>
                @endif
                @if($usaTaller)
                <button wire:click="$set('vistaActual','taller')"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                        background:{{ $vistaActual === 'taller' ? '#0f766e' : 'rgba(255,255,255,.2)' }};
                        color:white; display:flex; align-items:center; gap:5px;">
                    🔧 Taller
                    @if($vistaActual !== 'taller')
                        @php $activosTaller = \App\Models\TallerOrden::where('empresa_id', auth()->user()->getEmpresaActualId())->whereIn('estado',['pendiente','en_proceso','listo'])->count(); @endphp
                        @if($activosTaller > 0)
                            <span style="background:#ef4444; border-radius:99px; padding:1px 6px; font-size:10px;">{{ $activosTaller }}</span>
                        @endif
                    @endif
                </button>
                @if($vistaActual === 'taller')
                <button wire:click="abrirFormTaller"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                        background:#16a34a; color:white; display:flex; align-items:center; gap:5px;">
                    ➕ Nueva Orden
                </button>
                <button wire:click="$set('vistaActual','mesas')"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                        background:rgba(255,255,255,.2); color:white;">
                    🪑 Mesas
                </button>
                @endif
                @endif
            </div>
            @if($vistaActual === 'mesas' && !$mostrarDomicilios && $zonas->isNotEmpty())
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
    @if(!$mostrarDomicilios)
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
    @endif

    {{-- Cuentas pendientes: ocultas para mesero --}}
    @php $esMesero = auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor']); @endphp
    @if($cuentasPendientes->isNotEmpty() && ! $esMesero && ! $mostrarDomicilios)
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

    {{-- Panel domicilios del día --}}
    @if($mostrarDomicilios)
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @php
            $allDom   = collect($domiciliosHoy);
            $activos  = $allDom->whereIn('estado', ['en_cocina','listo','pendiente','asignado','en_camino']);
            $cerrados = $allDom->whereIn('estado', ['entregado','cancelado']);

            $labelEstado = [
                'en_cocina' => ['label'=>'En cocina',  'color'=>'#f59e0b', 'icon'=>'🍳'],
                'listo'     => ['label'=>'Listo',       'color'=>'#22c55e', 'icon'=>'✅'],
                'pendiente' => ['label'=>'Pendiente',   'color'=>'#6b7280', 'icon'=>'⏳'],
                'asignado'  => ['label'=>'Asignado',    'color'=>'#3b82f6', 'icon'=>'📋'],
                'en_camino' => ['label'=>'En camino',   'color'=>'#8b5cf6', 'icon'=>'🛵'],
                'entregado' => ['label'=>'Entregado',   'color'=>'#16a34a', 'icon'=>'✅'],
                'cancelado' => ['label'=>'Cancelado',   'color'=>'#ef4444', 'icon'=>'❌'],
            ];
        @endphp

        @if($allDom->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🛵</div>
                <div style="margin-top:12px; font-size:15px;">No hay domicilios hoy.</div>
            </div>
        @else

        {{-- Activos --}}
        @if($activos->isNotEmpty())
        <div style="font-size:11px; font-weight:700; color:#9333ea; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
            🟣 En curso ({{ $activos->count() }})
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:10px; margin-bottom:20px;">
            @foreach($activos as $d)
                @php $ei = $labelEstado[$d['estado']] ?? ['label'=>$d['estado'],'color'=>'#6b7280','icon'=>'?']; @endphp
                <div onclick="verDetalleDomicilioJS({{ json_encode($d) }})"
                     style="background:white; border:2px solid {{ $ei['color'] }}44; border-radius:12px; padding:12px 14px; box-shadow:0 1px 4px rgba(0,0,0,.06); cursor:pointer;
                        {{ $d['estado'] === 'listo' ? 'box-shadow:0 0 0 3px #16a34a, 0 0 14px #16a34a66; border-color:#16a34a;' : '' }}"
                     onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:10px; font-weight:700; background:{{ $ei['color'] }}22; color:{{ $ei['color'] }}; border-radius:99px; padding:2px 8px;">
                            {{ $ei['icon'] }} {{ $ei['label'] }}
                        </span>
                        <span style="font-size:10px; color:#94a3b8;"># Pedido {{ preg_replace('/[A-Z]+-/', '', $d['id']) }}</span>
                    </div>
                    <div style="font-size:13px; font-weight:700; color:#111827;">{{ $d['cliente'] }}</div>
                    @if($d['telefono'])
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">📞 {{ $d['telefono'] }}</div>
                    @endif
                    <div style="font-size:11px; color:#6b7280; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">📍 {{ $d['direccion'] }}</div>
                    @if($d['repartidor'])
                        <div style="font-size:11px; color:#3b82f6; margin-top:4px; font-weight:600;">🛵 {{ $d['repartidor'] }}</div>
                    @endif
                    @if($d['estado'] === 'listo')
                        <div style="margin-top:6px; background:#16a34a; color:white; border-radius:99px; padding:3px 10px; font-size:10px; font-weight:700; text-align:center;">
                            🍽️ LISTO PARA ENTREGAR
                        </div>
                    @endif
                    <div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; color:#64748b;">{{ $d['hora'] }}</span>
                        <span style="font-size:14px; font-weight:800; color:#0f766e;">${{ number_format($d['total'], 0, ',', '.') }}</span>
                    </div>
                    @if($d['origen'] === 'cocina' && !empty($d['mesa_id']))
                    <button onclick="event.stopPropagation(); window.location.href='{{ route('pos.mesa', $d['mesa_id']) }}';"
                            style="margin-top:8px; width:100%; border:none; border-radius:8px; padding:7px; font-size:12px; font-weight:700; cursor:pointer; background:#4338ca; color:white;">
                        💵 Ir a facturar
                    </button>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

        {{-- Cerrados --}}
        @if($cerrados->isNotEmpty())
        <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
            ✅ Finalizados hoy ({{ $cerrados->count() }})
        </div>
        <div style="display:flex; flex-direction:column; gap:6px;">
            @foreach($cerrados as $d)
                @php $ei = $labelEstado[$d['estado']] ?? ['label'=>$d['estado'],'color'=>'#6b7280','icon'=>'?']; @endphp
                <div onclick="verDetalleDomicilioJS({{ json_encode($d) }})"
                     style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; display:flex; align-items:center; gap:12px; cursor:pointer;"
                     onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
                    <span style="font-size:10px; font-weight:700; background:{{ $ei['color'] }}22; color:{{ $ei['color'] }}; border-radius:99px; padding:2px 8px; white-space:nowrap;">
                        {{ $ei['icon'] }} {{ $ei['label'] }}
                    </span>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:12px; font-weight:700; color:#374151;">{{ $d['cliente'] }}</div>
                        <div style="font-size:11px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">📍 {{ $d['direccion'] }}</div>
                    </div>
                    @if($d['repartidor'])
                        <div style="font-size:11px; color:#3b82f6; white-space:nowrap;">🛵 {{ $d['repartidor'] }}</div>
                    @endif
                    <div style="font-size:13px; font-weight:800; color:#374151; white-space:nowrap;">${{ number_format($d['total'], 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>
        @endif

        @endif
    </div>
    @elseif($vistaActual === 'taller')
    {{-- Modal formulario nueva orden de taller --}}
    @if($mostrarFormTaller)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.3);">
            {{-- Header modal --}}
            <div style="background:#0f766e; color:white; padding:16px 20px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:16px; font-weight:700;">🔧 Nueva orden de taller</div>
                <button wire:click="$set('mostrarFormTaller', false)"
                    style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:50%; width:28px; height:28px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center;">✕</button>
            </div>
            <div style="padding:20px;">
                @error('tallerNombre') <div style="background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; border-radius:8px; padding:8px 12px; margin-bottom:12px; font-size:13px;">{{ $message }}</div> @enderror
                @error('tallerPlaca')  <div style="background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; border-radius:8px; padding:8px 12px; margin-bottom:12px; font-size:13px;">{{ $message }}</div> @enderror

                {{-- Cliente --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px;">👤 Cliente</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Nombre *</label>
                        <input wire:model="tallerNombre" type="text" placeholder="Nombre del cliente"
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Teléfono</label>
                        <input wire:model="tallerTelefono" type="text" placeholder="3001234567"
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                </div>

                {{-- Vehículo --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px;">🚗 Vehículo</div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Placa *</label>
                        <input wire:model="tallerPlaca" type="text" placeholder="ABC-123"
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; font-weight:700; text-transform:uppercase; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Marca</label>
                        <input wire:model="tallerMarca" type="text" placeholder="Toyota, Renault..."
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Modelo</label>
                        <input wire:model="tallerModelo" type="text" placeholder="Corolla, Logan..."
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Color</label>
                        <input wire:model="tallerColor" type="text" placeholder="Blanco, Negro..."
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#6b7280; font-weight:600; display:block; margin-bottom:4px;">Kilometraje</label>
                        <input wire:model="tallerKm" type="number" placeholder="45000"
                            style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                </div>

                {{-- Diagnóstico --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px;">🔍 Diagnóstico</div>
                <textarea wire:model="tallerDiagnostico" placeholder="Describe el problema o el trabajo a realizar..."
                    rows="3"
                    style="width:100%; border:1.5px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; resize:vertical; box-sizing:border-box;"
                    onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1d5db'"></textarea>

                {{-- Botones --}}
                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button wire:click="guardarOrdenTaller"
                        style="flex:1; background:#0f766e; color:white; border:none; border-radius:10px; padding:12px; font-size:14px; font-weight:700; cursor:pointer;">
                        🔧 Guardar orden
                    </button>
                    <button wire:click="$set('mostrarFormTaller', false)"
                        style="flex:1; background:#e5e7eb; color:#374151; border:none; border-radius:10px; padding:12px; font-size:14px; font-weight:700; cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Panel taller integrado --}}
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @php
            $coloresTaller = [
                'pendiente'  => ['bg'=>'#fef3c7','border'=>'#fbbf24','badge'=>'#f59e0b','text'=>'#78350f','icon'=>'⏳'],
                'en_proceso' => ['bg'=>'#eff6ff','border'=>'#93c5fd','badge'=>'#3b82f6','text'=>'#1e40af','icon'=>'🔧'],
                'listo'      => ['bg'=>'#f0fdf4','border'=>'#86efac','badge'=>'#16a34a','text'=>'#14532d','icon'=>'✅'],
                'entregado'  => ['bg'=>'#f8fafc','border'=>'#cbd5e1','badge'=>'#64748b','text'=>'#475569','icon'=>'📦'],
                'cancelado'  => ['bg'=>'#fef2f2','border'=>'#fca5a5','badge'=>'#ef4444','text'=>'#7f1d1d','icon'=>'❌'],
            ];
            $tallerActivos  = $ordenesTaller->whereIn('estado', ['pendiente','en_proceso','listo']);
            $tallerHistorial = $ordenesTaller->whereIn('estado', ['entregado','cancelado']);
        @endphp

        @if($ordenesTaller->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🔧</div>
                <div style="margin-top:12px; font-size:15px;">No hay órdenes de taller.</div>
                <button wire:click="abrirFormTaller"
                    style="margin-top:16px; background:#0f766e; color:white; border:none; border-radius:10px; padding:10px 24px; font-size:14px; font-weight:700; cursor:pointer;">
                    ➕ Crear primera orden
                </button>
            </div>
        @else

        {{-- Activas --}}
        @if($tallerActivos->isNotEmpty())
        <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
            🔧 En taller ({{ $tallerActivos->count() }})
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px; margin-bottom:20px;">
            @foreach($tallerActivos as $to)
                @php $ct = $coloresTaller[$to->estado] ?? $coloresTaller['pendiente']; @endphp
                <div style="background:{{ $ct['bg'] }}; border:2px solid {{ $ct['border'] }}; border-radius:12px; padding:12px 14px;
                    {{ $to->estado === 'listo' ? 'box-shadow:0 0 0 3px #16a34a, 0 0 14px #16a34a55;' : '' }}">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:10px; font-weight:700; background:{{ $ct['badge'] }}; color:white; border-radius:99px; padding:2px 10px;">
                            {{ $ct['icon'] }} {{ ucfirst(str_replace('_',' ',$to->estado)) }}
                        </span>
                        <span style="font-size:10px; font-weight:700; color:{{ $ct['text'] }};"># {{ str_pad($to->numero_orden, 4, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div style="font-size:18px; font-weight:900; color:#0f766e;">{{ $to->placa }}</div>
                    <div style="font-size:12px; color:#374151; font-weight:600;">{{ trim($to->marca . ' ' . $to->modelo) ?: '—' }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:4px;">👤 {{ $to->cliente_nombre }}</div>
                    @if($to->cliente_telefono)
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">📞 {{ $to->cliente_telefono }}</div>
                    @endif
                    @if($to->diagnostico)
                        <div style="font-size:11px; color:#6b7280; margin-top:4px; background:rgba(0,0,0,.04); border-radius:6px; padding:4px 7px;">{{ Str::limit($to->diagnostico, 80) }}</div>
                    @endif
                    <div style="margin-top:8px; display:flex; gap:5px; flex-wrap:wrap;">
                        @if($to->estado !== 'entregado')
                        <a href="{{ route('taller.orden', $to->id) }}"
                            style="flex:1; border:none; border-radius:7px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#4f46e5; color:white; text-align:center; text-decoration:none; display:block;">
                            🔧 Abrir orden
                        </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Historial --}}
        @if($tallerHistorial->isNotEmpty())
        <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
            📦 Historial ({{ $tallerHistorial->count() }})
        </div>
        <div style="display:flex; flex-direction:column; gap:6px;">
            @foreach($tallerHistorial as $to)
                @php $ct = $coloresTaller[$to->estado] ?? $coloresTaller['entregado']; @endphp
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; display:flex; align-items:center; gap:12px;">
                    <span style="font-size:10px; font-weight:700; background:{{ $ct['badge'] }}22; color:{{ $ct['badge'] }}; border-radius:99px; padding:2px 8px; white-space:nowrap;">
                        {{ $ct['icon'] }} {{ ucfirst(str_replace('_',' ',$to->estado)) }}
                    </span>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:800; color:#0f766e;">{{ $to->placa }}</div>
                        <div style="font-size:11px; color:#374151;">{{ $to->cliente_nombre }} · {{ trim($to->marca . ' ' . $to->modelo) }}</div>
                    </div>
                    <div style="font-size:10px; color:#94a3b8; white-space:nowrap;">{{ $to->created_at->format('d/m/Y') }}</div>
                </div>
            @endforeach
        </div>
        @endif

        @endif
    </div>
    @else

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

                        $listoParaEntregar = false;
                        if ($ordenActiva) {
                            $itemsCocina = $ordenActiva->items->whereIn('estado_cocina', ['enviado', 'preparando', 'listo']);
                            $listoParaEntregar = $itemsCocina->isNotEmpty()
                                && $itemsCocina->every(fn($i) => $i->estado_cocina === 'listo')
                                && ! $ordenActiva->entregada;
                        }

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
    @endif {{-- fin mostrarDomicilios --}}

</div>

<script>
    function verDetalleDomicilioJS(d) {
        const fmt = function(n) { return '$' + Number(n).toLocaleString('es-CO', {maximumFractionDigits: 0}); };
        const pedidoNum = String(d.id).replace(/^[A-Za-z]+-/, '');

        var itemsHtml = '';
        if (d.items && d.items.length > 0) {
            itemsHtml = '<div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Productos</div>';
            d.items.forEach(function(i) {
                itemsHtml += '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">'
                    + '<div style="flex:1;text-align:left;">'
                    + '<div style="font-size:13px;color:#111827;font-weight:600;">' + i.nombre + '</div>'
                    + (i.nota ? '<div style="font-size:11px;color:#f59e0b;">📝 ' + i.nota + '</div>' : '')
                    + '<div style="font-size:11px;color:#9ca3af;">' + Number(i.cantidad).toFixed(0) + ' × ' + fmt(i.precio) + '</div>'
                    + '</div>'
                    + '<div style="font-size:13px;font-weight:700;color:#374151;margin-left:12px;">' + fmt(i.subtotal) + '</div>'
                    + '</div>';
            });
        } else {
            itemsHtml = '<div style="text-align:center;padding:20px;color:#9ca3af;">Sin detalle de ítems</div>';
        }

        var totalesHtml = '';
        if (Number(d.dom_costo) > 0 || Number(d.desechables) > 0) {
            totalesHtml += '<div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:4px;"><span>Subtotal</span><span>' + fmt(d.subtotal) + '</span></div>';
            if (Number(d.dom_costo) > 0) totalesHtml += '<div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:4px;"><span>Domicilio</span><span>' + fmt(d.dom_costo) + '</span></div>';
            if (Number(d.desechables) > 0) totalesHtml += '<div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:4px;"><span>Desechables</span><span>' + fmt(d.desechables) + '</span></div>';
        }
        totalesHtml += '<div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:#0f766e;padding-top:6px;border-top:1px solid #d1fae5;"><span>Total</span><span>' + fmt(d.total) + '</span></div>';

        Swal.fire({
            html: '<div style="text-align:left;">'
                + '<div style="background:#4338ca;color:white;padding:12px 16px;border-radius:10px 10px 0 0;margin:-1.25em -1.25em 14px -1.25em;">'
                + '<div style="font-size:15px;font-weight:700;">🛵 Pedido #' + pedidoNum + '</div>'
                + '<div style="font-size:12px;opacity:.8;margin-top:2px;">' + d.cliente + (d.telefono ? ' · 📞 ' + d.telefono : '') + '</div>'
                + '<div style="font-size:11px;opacity:.7;margin-top:2px;">📍 ' + d.direccion + '</div>'
                + (d.observaciones ? '<div style="font-size:11px;opacity:.85;margin-top:3px;background:rgba(255,255,255,.15);border-radius:5px;padding:2px 6px;">📝 ' + d.observaciones + '</div>' : '')
                + '</div>'
                + itemsHtml
                + '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e5e7eb;">' + totalesHtml + '</div>'
                + '</div>',
            showConfirmButton: false,
            showCloseButton: true,
            width: '480px',
            padding: '1.25em',
        });
    }

    document.addEventListener('livewire:init', () => {
        Livewire.on('warning', (msg) => {
            const text = Array.isArray(msg) ? msg[0] : msg;
            Swal.fire({ icon: 'warning', title: '⚠️ Atención', text: text, confirmButtonColor: '#f59e0b' });
        });
    });
</script>
