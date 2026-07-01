<div style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div style="padding:10px 16px; background:#0f766e; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-size:16px; font-weight:700; white-space:nowrap;">🔧 Taller</span>

            {{-- Búsqueda --}}
            <input wire:model.live.debounce.300ms="busqueda" type="text" placeholder="Buscar placa o cliente..."
                style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; width:190px; outline:none; color:#1f2937;">

            {{-- Rango de fechas --}}
            <div style="display:flex; align-items:center; gap:4px;">
                <input wire:model.live="fechaDesde" type="date"
                    style="border:none; border-radius:10px; padding:4px 8px; font-size:11px; outline:none; color:#1f2937; height:28px;">
                <span style="font-size:11px;">→</span>
                <input wire:model.live="fechaHasta" type="date"
                    style="border:none; border-radius:10px; padding:4px 8px; font-size:11px; outline:none; color:#1f2937; height:28px;">
                @if($fechaDesde || $fechaHasta)
                <button wire:click="limpiarFechas"
                    style="border:none; border-radius:99px; background:rgba(255,255,255,.25); color:white; font-size:14px; width:24px; height:24px; cursor:pointer; padding:0; line-height:1;">×</button>
                @endif
            </div>

            {{-- Separador --}}
            <span style="width:1px; height:20px; background:rgba(255,255,255,.3); display:inline-block;"></span>

            {{-- Filtros estado --}}
            @foreach(['activas'=>'🔧 En ejecución','entregado'=>'💰 Cobradas',''=>'📋 Todas'] as $val => $lbl)
            <button wire:click="$set('filtroEstado','{{ $val }}')"
                style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;
                    background:{{ $filtroEstado === $val ? 'white' : 'rgba(255,255,255,.2)' }};
                    color:{{ $filtroEstado === $val ? '#0f766e' : 'white' }};">
                {{ $lbl }}
                @if($val === 'activas')
                    @php $nPend = \App\Models\TallerOrden::where('empresa_id', auth()->user()->getEmpresaActualId())->where('estado','!=','entregado')->count(); @endphp
                    @if($nPend > 0)<span style="background:#ef4444; border-radius:99px; padding:1px 6px; font-size:10px; color:white; margin-left:3px;">{{ $nPend }}</span>@endif
                @endif
            </button>
            @endforeach

            {{-- Reporte PDF del listado actual --}}
            <a href="{{ route('taller.reporte.pdf', ['estado' => $filtroEstado ?: 'todos', 'desde' => $fechaDesde, 'hasta' => $fechaHasta, 'busqueda' => $busqueda]) }}"
               target="_blank"
               style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; background:rgba(255,255,255,.2); color:white; text-decoration:none; white-space:nowrap;">
                📄 Reporte PDF
            </a>
        </div>
    </div>

    {{-- Grid de órdenes --}}
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @if($ordenes->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🔧</div>
                <div style="margin-top:12px; font-size:15px; font-weight:600;">
                    @if($filtroEstado === 'activas') No hay órdenes en ejecución.
                    @elseif($filtroEstado === 'entregado') No hay órdenes cobradas en este período.
                    @else No hay órdenes de taller.
                    @endif
                </div>
                <div style="font-size:12px; color:#cbd5e1; margin-top:6px;">Las órdenes se crean desde el POS con el botón "Ingresar".</div>
            </div>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:14px;">
            @foreach($ordenes as $orden)
                @php
                    $esCobrada  = $orden->estado === 'entregado';
                    $colores = [
                        'pendiente'  => ['bg'=>'#fef3c7','border'=>'#fbbf24','badge'=>'#f59e0b','text'=>'#78350f','icon'=>'⏳','label'=>'Pendiente'],
                        'en_proceso' => ['bg'=>'#eff6ff','border'=>'#93c5fd','badge'=>'#3b82f6','text'=>'#1e40af','icon'=>'🔧','label'=>'Ejecutada'],
                        'listo'      => ['bg'=>'#eff6ff','border'=>'#93c5fd','badge'=>'#3b82f6','text'=>'#1e40af','icon'=>'🔧','label'=>'Ejecutada'],
                        'entregado'  => ['bg'=>'#f0fdf4','border'=>'#86efac','badge'=>'#16a34a','text'=>'#14532d','icon'=>'💰','label'=>'Cobrada'],
                        'cancelado'  => ['bg'=>'#fef2f2','border'=>'#fca5a5','badge'=>'#ef4444','text'=>'#7f1d1d','icon'=>'❌','label'=>'Cancelada'],
                    ];
                    $c        = $colores[$orden->estado] ?? $colores['pendiente'];
                    $totalRep = $orden->repuestos->sum('subtotal');
                @endphp
                <div style="background:{{ $c['bg'] }}; border:2px solid {{ $c['border'] }}; border-radius:14px; padding:16px; position:relative;">

                    {{-- Badge estado + número --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:11px; font-weight:700; background:{{ $c['badge'] }}; color:white; border-radius:99px; padding:3px 12px;">
                            {{ $c['icon'] }} {{ $c['label'] }}
                        </span>
                        <span style="font-size:13px; font-weight:800; color:{{ $c['text'] }};">
                            # {{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    {{-- Vehículo --}}
                    <div style="font-size:22px; font-weight:900; color:#0f766e; letter-spacing:.05em;">{{ $orden->placa }}</div>
                    <div style="font-size:12px; color:#374151; font-weight:600; margin-top:2px;">
                        {{ trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '')) ?: '—' }}
                        @if($orden->color) · <span style="color:#6b7280;">{{ $orden->color }}</span> @endif
                        @if($orden->km_ingreso) · <span style="color:#6b7280;">{{ number_format($orden->km_ingreso) }} km</span> @endif
                    </div>

                    {{-- Cliente --}}
                    <div style="margin-top:8px; font-size:13px; color:#1f2937; font-weight:600;">
                        👤 {{ $orden->cliente_nombre }}
                        @if($orden->cliente_telefono)
                            <span style="font-size:11px; color:#6b7280; font-weight:400;"> · 📞 {{ $orden->cliente_telefono }}</span>
                        @endif
                    </div>

                    {{-- Diagnóstico --}}
                    @if($orden->diagnostico)
                    <div style="margin-top:8px; font-size:12px; color:#4b5563; background:rgba(0,0,0,.04); border-radius:8px; padding:7px 10px; line-height:1.5;">
                        📋 {{ $orden->diagnostico }}
                    </div>
                    @endif

                    @if($orden->observaciones)
                    <div style="margin-top:4px; font-size:11px; color:#6b7280; font-style:italic; padding:0 4px;">
                        {{ $orden->observaciones }}
                    </div>
                    @endif

                    {{-- Repuestos / Servicios detalle --}}
                    @if($orden->repuestos->isNotEmpty())
                    <div style="margin-top:10px; border-top:1px solid rgba(0,0,0,.07); padding-top:8px;">
                        <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:5px;">Repuestos / Servicios</div>
                        @foreach($orden->repuestos as $rep)
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#374151; padding:2px 0;">
                            <span>🔩 {{ $rep->descripcion }} <span style="color:#9ca3af;">× {{ $rep->cantidad }}</span></span>
                            <span style="font-weight:700; color:#0f766e;">${{ number_format($rep->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @endforeach
                        <div style="display:flex; justify-content:flex-end; margin-top:6px; padding-top:6px; border-top:1px solid rgba(0,0,0,.07);">
                            <span style="font-size:14px; font-weight:800; color:#0f766e;">Total: ${{ number_format($totalRep, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- Fecha --}}
                    <div style="font-size:10px; color:#9ca3af; margin-top:8px;">
                        Ingresó: {{ $orden->created_at->format('d/m/Y h:i A') }}
                        @if($orden->entregado_at)
                            · Cobrada: {{ \Carbon\Carbon::parse($orden->entregado_at)->format('d/m/Y h:i A') }}
                        @endif
                    </div>

                    {{-- Acciones --}}
                    <div style="display:flex; gap:6px; margin-top:12px; flex-wrap:wrap;">
                        @if(!$esCobrada)
                        <button wire:click="abrirOrden({{ $orden->id }})"
                            style="flex:1; border:none; border-radius:8px; padding:8px 6px; font-size:12px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                            🛒 Abrir en POS
                        </button>
                        @if($orden->estado === 'pendiente')
                        <button wire:click="cambiarEstado({{ $orden->id }},'en_proceso')"
                            style="flex:1; border:none; border-radius:8px; padding:8px 6px; font-size:12px; font-weight:700; cursor:pointer; background:#3b82f6; color:white;">
                            🔧 Iniciar
                        </button>
                        @endif
                        @endif

                        {{-- PDF de esta orden --}}
                        <a href="{{ route('taller.orden.pdf', $orden->id) }}" target="_blank"
                           style="flex:1; border:none; border-radius:8px; padding:8px 6px; font-size:12px; font-weight:700; cursor:pointer; background:#7c3aed; color:white; text-decoration:none; text-align:center;">
                            📄 PDF
                        </a>

                        @if($esCobrada && $orden->factura_id)
                        <a href="{{ route('factura.ver', $orden->factura_id) }}" target="_blank"
                           style="flex:1; border:none; border-radius:8px; padding:8px 6px; font-size:12px; font-weight:700; cursor:pointer; background:#16a34a; color:white; text-decoration:none; text-align:center;">
                            🧾 Factura
                        </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Modal editar orden --}}
    @if($modalOrden)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto;">
            <div style="background:#0f766e; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">🔧 Editar orden #{{ str_pad($ordenId ?? 0, 4, '0', STR_PAD_LEFT) }}</span>
                <button wire:click="$set('modalOrden',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Nombre cliente *</label>
                        <input wire:model="clienteNombre" type="text"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                        @error('clienteNombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Teléfono</label>
                        <input wire:model="clienteTelefono" type="text"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Placa *</label>
                        <input wire:model="placa" type="text" oninput="this.value=this.value.replace(/-/g,'').toUpperCase()"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:14px; font-weight:700; margin-top:3px;">
                        @error('placa') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Marca</label>
                        <input wire:model="marca" type="text" list="dl_marcas_modal"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                        <datalist id="dl_marcas_modal">
                            @foreach(['Yamaha','Honda','Suzuki','Kawasaki','AKT','TVS','Bajaj','Hero','KTM','Benelli','Royal Enfield','Chevrolet','Renault','Toyota','Mazda','Kia','Hyundai','Ford','Volkswagen','Nissan','Mitsubishi','BYD','Chery','DFSK','JAC'] as $m)
                            <option value="{{ $m }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Modelo</label>
                        <input wire:model="modelo" type="text" list="dl_modelos_modal"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                        <datalist id="dl_modelos_modal">
                            @foreach(['XTZ 150','FZ 16','R15 V3','CB 190R','CB 125F','Pulsar NS 200','Pulsar 200','Duke 200','Discover 125','Platino 100','Spark','Onix','Tracker','Duster','Logan','Sandero','Corolla','Yaris','Hilux','Mazda 3','Mazda 6','CX-5','Sportage','Rio','Cerato','Tucson','Accent'] as $m)
                            <option value="{{ $m }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Color</label>
                        <input wire:model="color" type="text"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Kilometraje</label>
                        <input wire:model="kmIngreso" type="text"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>
                <textarea wire:model="diagnostico" rows="2" placeholder="Diagnóstico..."
                    style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; resize:none; margin-bottom:8px;"></textarea>
                <textarea wire:model="observaciones" rows="2" placeholder="Observaciones..."
                    style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; resize:none; margin-bottom:14px;"></textarea>

                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button wire:click="$set('modalOrden',false)"
                        style="border:1px solid #d1d5db; border-radius:10px; padding:9px 20px; font-size:13px; font-weight:600; cursor:pointer; background:white; color:#374151;">
                        Cancelar
                    </button>
                    <button wire:click="guardarOrden"
                        style="border:none; border-radius:10px; padding:9px 24px; font-size:13px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                        💾 Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('success', (msg) => {
            const text = Array.isArray(msg) ? msg[0] : msg;
            Swal.fire({ icon: 'success', title: text, timer: 1800, showConfirmButton: false });
        });
        Livewire.on('warning', (msg) => {
            const text = Array.isArray(msg) ? msg[0] : msg;
            Swal.fire({ icon: 'warning', title: '⚠️ Atención', text: text, confirmButtonColor: '#f59e0b' });
        });
    });
</script>
