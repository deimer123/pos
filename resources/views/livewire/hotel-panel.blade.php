<div style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div style="padding:10px 16px; background:#7c3aed; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-size:16px; font-weight:700; white-space:nowrap;">🏨 Hotel</span>

            <input wire:model.live.debounce.300ms="busqueda" type="text" placeholder="Buscar habitación..."
                style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; width:180px; outline:none; color:#1f2937;">

            @if(count($zonasDisponibles) > 0)
            <select wire:model.live="filtroZona"
                style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; outline:none; color:#1f2937;">
                <option value="">Todas las zonas</option>
                @foreach($zonasDisponibles as $z)
                    <option value="{{ $z }}">{{ $z }}</option>
                @endforeach
            </select>
            @endif

            <span style="width:1px; height:20px; background:rgba(255,255,255,.3);"></span>

            <button wire:click="$set('vistaActiva','habitaciones')"
                style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $vistaActiva === 'habitaciones' ? 'white' : 'rgba(255,255,255,.2)' }};
                    color:{{ $vistaActiva === 'habitaciones' ? '#7c3aed' : 'white' }};">
                🛏️ Habitaciones
            </button>
            <button wire:click="$set('vistaActiva','calendario')"
                style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $vistaActiva === 'calendario' ? 'white' : 'rgba(255,255,255,.2)' }};
                    color:{{ $vistaActiva === 'calendario' ? '#7c3aed' : 'white' }};">
                📅 Calendario
            </button>

            <div style="flex:1;"></div>

            <button wire:click="abrirNuevaReserva"
                style="border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; background:#16a34a; color:white; white-space:nowrap;">
                ➕ Nueva reserva
            </button>
        </div>
    </div>

    {{-- Alertas: check-in que no ha llegado / salidas programadas para hoy --}}
    @if($alertas['checkinsPendientes']->isNotEmpty() || $alertas['salidasHoy']->isNotEmpty())
    <div style="padding:10px 16px; display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
        @if($alertas['checkinsPendientes']->isNotEmpty())
        <div style="background:#fef3c7; border:1px solid #f59e0b; border-radius:10px; padding:8px 12px;">
            <div style="font-size:12px; font-weight:700; color:#92400e; margin-bottom:6px;">
                ⚠️ {{ $alertas['checkinsPendientes']->count() }} check-in pendiente(s)
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach($alertas['checkinsPendientes'] as $r)
                <div style="background:white; border-radius:8px; padding:4px 8px; display:flex; align-items:center; gap:8px; font-size:11px;">
                    <span>
                        Hab. {{ $r->habitacion?->numero ?? '—' }} · {{ $r->huesped_nombre }}
                        · esperado {{ \Illuminate\Support\Carbon::parse($r->fecha_checkin)->format('d/m') }}
                    </span>
                    <button type="button" wire:click="confirmarCheckin({{ $r->id }})"
                        style="border:none; border-radius:999px; padding:3px 10px; font-size:10px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                        🔑 Entrada
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($alertas['salidasHoy']->isNotEmpty())
        <div style="background:#dbeafe; border:1px solid #3b82f6; border-radius:10px; padding:8px 12px;">
            <div style="font-size:12px; font-weight:700; color:#1e40af; margin-bottom:6px;">
                🧾 {{ $alertas['salidasHoy']->count() }} salida(s) programada(s) para hoy
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach($alertas['salidasHoy'] as $r)
                <div style="background:white; border-radius:8px; padding:4px 8px; display:flex; align-items:center; gap:8px; font-size:11px;">
                    <span>Hab. {{ $r->habitacion?->numero ?? '—' }} · {{ $r->huesped_nombre }}</span>
                    <button type="button" wire:click="irAFacturar({{ $r->id }})"
                        style="border:none; border-radius:999px; padding:3px 10px; font-size:10px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                        🧾 Salida
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Vista Habitaciones --}}
    @if($vistaActiva === 'habitaciones')
    <div style="flex:1; overflow-y:auto; padding:16px;">

        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px;">
            <button wire:click="$set('filtroEstado','todas')"
                style="border:1px solid #e2e8f0; border-radius:99px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $filtroEstado === 'todas' ? '#334155' : 'white' }}; color:{{ $filtroEstado === 'todas' ? 'white' : '#334155' }};">
                Todas ({{ $conteoEstados['todas'] }})
            </button>
            <button wire:click="$set('filtroEstado','libre')"
                style="border:1px solid #86efac; border-radius:99px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $filtroEstado === 'libre' ? '#16a34a' : '#f0fdf4' }}; color:{{ $filtroEstado === 'libre' ? 'white' : '#15803d' }};">
                🟢 Disponibles ({{ $conteoEstados['libre'] }})
            </button>
            <button wire:click="$set('filtroEstado','ocupada')"
                style="border:1px solid #fca5a5; border-radius:99px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $filtroEstado === 'ocupada' ? '#dc2626' : '#fef2f2' }}; color:{{ $filtroEstado === 'ocupada' ? 'white' : '#991b1b' }};">
                🔴 Ocupadas ({{ $conteoEstados['ocupada'] }})
            </button>
            <button wire:click="$set('filtroEstado','reservada')"
                style="border:1px solid #fde68a; border-radius:99px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;
                    background:{{ $filtroEstado === 'reservada' ? '#f59e0b' : '#fffbeb' }}; color:{{ $filtroEstado === 'reservada' ? 'white' : '#92400e' }};">
                🟡 Reservadas ({{ $conteoEstados['reservada'] }})
            </button>
        </div>

        @if($filtroEstado === 'reservada')
        {{-- Reservadas: TODAS las reservas hechas (hoy, atrasadas o a futuro),
             no solo la activa hoy en la habitación — una pieza puede tener
             varias reservas en fila. Se lista por reserva, no por pieza. --}}
            @if($reservas->isEmpty())
                <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                    <div style="font-size:48px;">📅</div>
                    <div style="margin-top:12px; font-size:15px; font-weight:600;">No hay reservas pendientes.</div>
                </div>
            @else
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px;">
                @foreach($reservas as $r)
                    @php $esFutura = $r->fecha_checkin->isAfter(now()->startOfDay()); @endphp
                    <div style="background:white; border-radius:14px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); border:3px solid #f59e0b;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                            <div style="font-size:18px; font-weight:900; color:#1f2937;">🚪 {{ $r->habitacion->numero ?? '?' }}</div>
                            <span style="font-size:10px; font-weight:800; background:#fef3c7; color:#92400e; border-radius:99px; padding:3px 10px; white-space:nowrap;">
                                {{ $esFutura ? '📅 A futuro' : '🟡 Reservada' }}
                            </span>
                        </div>
                        <div style="font-weight:700; font-size:13px; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">👤 {{ $r->huesped_nombre }}</div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:3px; margin-bottom:10px;">
                            {{ $r->fecha_checkin->format('d/m/Y') }} → {{ $r->fecha_checkout?->format('d/m/Y') ?? '¿?' }}
                            · {{ $r->numero_personas }} pers.
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:10px;">
                            <span style="font-size:11px; color:#6b7280;">Total estimado</span>
                            <span style="font-size:15px; font-weight:900; color:#16a34a;">${{ number_format($r->total_estimado, 0, ',', '.') }}</span>
                        </div>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button wire:click="confirmarCheckin({{ $r->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Entrada
                            </button>
                            <button wire:click="abrirEditarReserva({{ $r->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#eff6ff; color:#2563eb;">
                                ✏️ Editar
                            </button>
                            @if(auth()->user()->hasRole('admin_empresa'))
                            <button type="button"
                                x-on:click="Swal.fire({title:'¿Cancelar esta reserva?',text:'Esta acción no se puede deshacer.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, cancelar',cancelButtonText:'No',confirmButtonColor:'#ef4444'}).then(r=>{if(r.isConfirmed){$wire.cancelarReserva({{ $r->id }});}})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#fef2f2; color:#ef4444;">
                                ✕ Cancelar
                            </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        @elseif($habitaciones->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🏨</div>
                @if($conteoEstados['todas'] === 0)
                    <div style="margin-top:12px; font-size:15px; font-weight:600;">No hay habitaciones registradas.</div>
                    <div style="font-size:12px; color:#cbd5e1; margin-top:6px;">Créalas en <strong>Administración → Hotel → Habitaciones</strong>.</div>
                @else
                    <div style="margin-top:12px; font-size:15px; font-weight:600;">No hay habitaciones en este filtro.</div>
                @endif
            </div>
        @elseif($filtroEstado === 'libre')
        {{-- Disponibles: tarjeta chica, solo lo esencial para dar entrada rápido --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
            @foreach($habitaciones as $h)
                @php
                    $camas = [];
                    if ($h->camas_dobles > 0) $camas[] = $h->camas_dobles . ' doble' . ($h->camas_dobles > 1 ? 's' : '');
                    if ($h->camas_sencillas > 0) $camas[] = $h->camas_sencillas . ' sencilla' . ($h->camas_sencillas > 1 ? 's' : '');
                    $camasTexto = implode(' · ', $camas);
                @endphp
                <div style="background:white; border-radius:12px; padding:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); border:3px solid #22c55e;">
                    <div style="font-size:15px; font-weight:900; color:#1f2937;">🚪 {{ $h->numero }}</div>
                    <div style="display:flex; align-items:center; gap:6px; font-size:11px; color:#6b7280; margin:4px 0 2px;">
                        <span>👥 {{ $h->capacidad_maxima }}</span>
                        @if($h->tiene_aire)<span>❄️</span>@endif
                        @if($h->tiene_ventilador)<span>🌀</span>@endif
                    </div>
                    @if($camasTexto)
                    <div style="font-size:10px; color:#94a3b8; margin-bottom:6px;">🛏️ {{ $camasTexto }}</div>
                    @endif
                    @if($h->proxima_reserva)
                    <div style="font-size:9px; font-weight:700; color:#92400e; background:#fef3c7; border-radius:6px; padding:3px 6px; margin-bottom:6px;">
                        ⚠️ Reserva {{ $h->proxima_reserva->fecha_checkin->format('d/m') }}
                    </div>
                    @endif
                    <div style="font-size:11px; font-weight:800; color:#7c3aed; margin-bottom:8px;">
                        ${{ number_format($h->precio_desde, 0, ',', '.') }}/noche
                    </div>
                    <button wire:click="abrirNuevaReserva({{ $h->id }}, true)"
                        style="width:100%; border:none; border-radius:8px; padding:6px; font-size:10px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                        🔑 Entrada
                    </button>
                </div>
            @endforeach
        </div>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:12px;">
            @foreach($habitaciones as $h)
                @php
                    $colores = [
                        'libre'     => ['border'=>'#22c55e','badge'=>'#dcfce7','badgeText'=>'#15803d','icon'=>'🟢','label'=>'Libre'],
                        'reservada' => ['border'=>'#f59e0b','badge'=>'#fef3c7','badgeText'=>'#92400e','icon'=>'🟡','label'=>'Reservada'],
                        'ocupada'   => ['border'=>'#ef4444','badge'=>'#fee2e2','badgeText'=>'#991b1b','icon'=>'🔴','label'=>'Ocupada'],
                    ];
                    $c = $colores[$h->estado_actual] ?? $colores['libre'];
                    $r = $h->reserva_activa;
                @endphp
                <div style="background:white; border-radius:14px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); border:3px solid {{ $c['border'] }};">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <div>
                            <div style="font-size:18px; font-weight:900; color:#1f2937; line-height:1.15;">🚪 {{ $h->numero }}</div>
                            @if($h->zona)
                            <div style="font-size:11px; color:#94a3b8; margin-top:2px;">📍 {{ $h->zona }}</div>
                            @endif
                        </div>
                        <span style="font-size:10px; font-weight:800; background:{{ $c['badge'] }}; color:{{ $c['badgeText'] }}; border-radius:99px; padding:3px 10px; white-space:nowrap;">
                            {{ $c['icon'] }} {{ $c['label'] }}
                        </span>
                    </div>

                    @php
                        $camas = [];
                        if ($h->camas_dobles > 0) $camas[] = $h->camas_dobles . ' doble' . ($h->camas_dobles > 1 ? 's' : '');
                        if ($h->camas_sencillas > 0) $camas[] = $h->camas_sencillas . ' sencilla' . ($h->camas_sencillas > 1 ? 's' : '');
                        $camasTexto = implode(' · ', $camas);
                    @endphp
                    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#6b7280; margin-bottom:2px;">
                        <span>👥 {{ $h->capacidad_maxima }}</span>
                        @if($h->tiene_aire)<span title="Aire disponible">❄️</span>@endif
                        @if($h->tiene_ventilador)<span title="Ventilador disponible">🌀</span>@endif
                        <span style="margin-left:auto; font-weight:800; color:#7c3aed;">${{ number_format($h->precio_desde, 0, ',', '.') }}<span style="font-weight:600; color:#a78bfa;">/noche</span></span>
                    </div>
                    @if($camasTexto)
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;">🛏️ {{ $camasTexto }}</div>
                    @endif

                    @if($r)
                    <div style="background:#f8fafc; border-radius:10px; padding:10px; margin-bottom:10px;">
                        <div style="font-weight:700; font-size:13px; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">👤 {{ $r->huesped_nombre }}</div>
                        <div style="font-size:11px; color:#94a3b8; margin-top:3px;">
                            {{ $r->fecha_checkin->format('d/m') }} → {{ $r->fecha_checkout?->format('d/m') ?? '¿?' }}
                            · {{ $r->numero_personas }} pers. · {{ $r->numero_noches }} noche(s)
                            @if($r->total_consumos > 0)
                                · +${{ number_format($r->total_consumos, 0, ',', '.') }} consumo(s)
                            @endif
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0;">
                            <span style="font-size:11px; color:#6b7280;">
                                Saldo
                                @if($r->abono_monto > 0)
                                    <span style="color:#d97706;">(abono ${{ number_format($r->abono_monto, 0, ',', '.') }})</span>
                                @endif
                            </span>
                            <span style="font-size:17px; font-weight:900; color:{{ $r->saldo_pendiente > 0 ? '#dc2626' : '#16a34a' }};">${{ number_format($r->saldo_pendiente, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif

                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        @if(! $r)
                            <button wire:click="abrirNuevaReserva({{ $h->id }}, true)"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Entrada ahora
                            </button>
                        @elseif($r->estado === 'reservada')
                            <button wire:click="confirmarCheckin({{ $r->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Entrada
                            </button>
                            <button wire:click="abrirEditarReserva({{ $r->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#eff6ff; color:#2563eb;">
                                ✏️ Editar
                            </button>
                            @if(auth()->user()->hasRole('admin_empresa'))
                            <button type="button"
                                x-on:click="Swal.fire({title:'¿Cancelar esta reserva?',text:'Esta acción no se puede deshacer.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, cancelar',cancelButtonText:'No',confirmButtonColor:'#ef4444'}).then(r=>{if(r.isConfirmed){$wire.cancelarReserva({{ $r->id }});}})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#fef2f2; color:#ef4444;">
                                ✕ Cancelar
                            </button>
                            @endif
                        @else
                            <button wire:click="irAFacturar({{ $r->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                                🧾 Salida / Facturar
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- Vista Calendario --}}
    @if($vistaActiva === 'calendario')
    <div style="flex:1; overflow:auto; padding:16px;">
        <div style="display:flex; gap:10px; align-items:end; margin-bottom:14px; flex-wrap:wrap;">
            <div>
                <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Desde</label>
                <input wire:model.live="calDesde" type="date"
                    style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
            </div>
            <div>
                <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Hasta</label>
                <input wire:model.live="calHasta" type="date"
                    style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
            </div>
        </div>

        @if(empty($calendario))
            <div style="text-align:center; padding:40px; color:#94a3b8;">No hay habitaciones para mostrar.</div>
        @else
        <div style="overflow-x:auto; background:white; border-radius:12px; border:1px solid #e5e7eb;">
            <table style="border-collapse:collapse; font-size:11px; width:100%;">
                <thead>
                    <tr>
                        <th style="position:sticky; left:0; background:#f1f5f9; padding:8px; text-align:left; border:1px solid #e2e8f0; min-width:80px;">Habitación</th>
                        @php
                            $diasAbrev = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
                            $coloresDia = ['#f0fdf4', '#eff6ff']; // verde / azul, alternando por día
                            $coloresFila = ['#fee2e2', '#fef9c3']; // rojo / amarillo, alternando por habitación
                        @endphp
                        @foreach($diasCalendario as $i => $dia)
                            <th style="padding:6px; text-align:center; border:1px solid #e2e8f0; min-width:56px; color:#6b7280; background:{{ $coloresDia[$i % 2] }};">
                                {{ $dia->format('d/m') }}<br><span style="font-weight:400;">{{ $diasAbrev[$dia->dayOfWeek] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($calendario as $fi => $fila)
                        <tr>
                            <td style="position:sticky; left:0; padding:8px; font-weight:700; border:1px solid #e2e8f0; border-left:5px solid {{ $coloresFila[$fi % 2] === '#fee2e2' ? '#ef4444' : '#eab308' }}; background:{{ $coloresFila[$fi % 2] }};">
                                🚪 {{ $fila['habitacion']->numero }}
                            </td>
                            @foreach($fila['celdas'] as $i => $celda)
                                @php
                                    $res = $celda['reserva'];
                                    $bg = $res ? ($res->estado === 'checkin' ? '#fca5a5' : '#fde68a') : $coloresDia[$i % 2];
                                @endphp
                                <td title="{{ $res ? $res->huesped_nombre . ' (clic para ver la reserva)' : 'Libre' }}"
                                    style="padding:6px; text-align:center; border:1px solid #e2e8f0; background:{{ $bg }}; cursor:pointer;"
                                    wire:click="{{ $res ? 'verReserva(' . $res->id . ')' : 'abrirNuevaReserva(' . $fila['habitacion']->id . ')' }}">
                                    @if($res)
                                        <span style="font-size:9px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; max-width:56px;">{{ $res->huesped_nombre }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display:flex; gap:14px; margin-top:10px; font-size:11px; color:#6b7280; flex-wrap:wrap;">
            <span><span style="display:inline-block; width:10px; height:10px; background:#f0fdf4; border:1px solid #86efac; border-radius:2px; vertical-align:middle;"></span> Libre</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:#fde68a; border-radius:2px; vertical-align:middle;"></span> Reservada</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:#fca5a5; border-radius:2px; vertical-align:middle;"></span> Ocupada (con entrada)</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:#ef4444; border-radius:2px; vertical-align:middle;"></span> / <span style="display:inline-block; width:10px; height:10px; background:#eab308; border-radius:2px; vertical-align:middle;"></span> Franja de color por habitación (para diferenciar filas)</span>
        </div>
        @endif
    </div>
    @endif

    {{-- Modal Nueva/Editar Reserva --}}
    @if($modalReserva)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1200; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto;">
            <div style="background:{{ $resInmediato ? '#f59e0b' : '#16a34a' }}; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">
                    @if($reservaId) ✏️ Editar reserva
                    @elseif($resInmediato) 🔑 Entrada ahora
                    @else 📅 Nueva reserva
                    @endif
                </span>
                <button wire:click="$set('modalReserva',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Habitación *</label>
                    @if($resHabitacionBloqueada && $this->habitacionSeleccionada)
                        @php $hs = $this->habitacionSeleccionada; @endphp
                        <div style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:0 10px; font-size:13px; box-sizing:border-box; background:#f8fafc; display:flex; align-items:center;">
                            <span>🚪 {{ $hs->numero }}
                                @if($hs->tiene_aire) ❄️ @endif
                                @if($hs->tiene_ventilador) 🌀 @endif
                                · hasta {{ $hs->capacidad_maxima }} pers.
                            </span>
                        </div>
                    @else
                        <select wire:model.live="resHabitacionId"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                            <option value="">Selecciona...</option>
                            @foreach($todasHabitaciones as $h)
                                <option value="{{ $h->id }}">🚪 {{ $h->numero }}
                                    @if($h->tiene_aire) ❄️ @endif
                                    @if($h->tiene_ventilador) 🌀 @endif
                                    · hasta {{ $h->capacidad_maxima }} pers. · desde ${{ number_format($h->precio_desde, 0, ',', '.') }}/noche</option>
                            @endforeach
                        </select>
                    @endif
                    @error('resHabitacionId') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                @if($this->habitacionSeleccionada)
                    @php $hSel = $this->habitacionSeleccionada; @endphp

                    <div style="background:#f0fdf4; border-radius:8px; padding:10px 12px; margin-bottom:12px; text-align:center;">
                        <div style="font-size:10px; color:#16a34a; text-transform:uppercase; font-weight:700;">Precio habitación sola (1 persona)</div>
                        <div style="font-size:20px; font-weight:900; color:#16a34a;">${{ number_format($hSel->precio_desde, 0, ',', '.') }} / noche</div>
                    </div>

                    @if($resInmediato && $this->proximaReservaSeleccionada)
                    <div style="background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:11px; color:#92400e;">
                        ⚠️ Esta habitación ya tiene una reserva para el <b>{{ $this->proximaReservaSeleccionada->fecha_checkin->format('d/m/Y') }}</b>
                        ({{ $this->proximaReservaSeleccionada->huesped_nombre }}). Solo está disponible hasta esa fecha — avísale al huésped.
                    </div>
                    @endif

                    @if($hSel->tiene_aire || $hSel->tiene_ventilador)
                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">¿Con aire o con ventilador?</label>
                        <select wire:model.live="resClimatizacion"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                            @foreach($hSel->opcionesClimatizacion() as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">¿Cuántas personas se van a {{ $resInmediato ? 'hospedar' : 'quedar' }}? *</label>
                        <input wire:model.live="resNumeroPersonas" type="number" min="1"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('resNumeroPersonas') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>

                    @if(! $resInmediato)
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Entrada *</label>
                            <input wire:model.live="resFechaCheckin" type="date"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Salida</label>
                            <input wire:model.live="resFechaCheckout" type="date"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                            <div style="font-size:9px; color:#9ca3af; margin-top:2px;">No importa si se deja en blanco.</div>
                            @error('resFechaCheckout') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    @endif

                    <div style="background:#f0fdf4; border-radius:8px; padding:12px; margin-bottom:12px; text-align:center;">
                        @if(! $resInmediato && $resFechaCheckout)
                            <div style="font-size:10px; color:#16a34a; text-transform:uppercase; font-weight:700;">Total estimado</div>
                            <div style="font-size:22px; font-weight:900; color:#16a34a;">${{ number_format($this->totalEstimadoReserva, 0, ',', '.') }}</div>
                            <div style="font-size:10px; color:#6b7280;">
                                ${{ number_format($this->precioNocheReserva, 0, ',', '.') }} / noche
                                × {{ \Illuminate\Support\Carbon::parse($resFechaCheckin)->diffInDays(\Illuminate\Support\Carbon::parse($resFechaCheckout)) ?: 1 }} noche(s)
                                para {{ $resNumeroPersonas ?: 1 }} persona(s)
                            </div>
                        @else
                            <div style="font-size:10px; color:#16a34a; text-transform:uppercase; font-weight:700;">Precio por noche</div>
                            <div style="font-size:22px; font-weight:900; color:#16a34a;">${{ number_format($this->precioNocheReserva, 0, ',', '.') }}</div>
                            <div style="font-size:10px; color:#6b7280;">para {{ $resNumeroPersonas ?: 1 }} persona(s) · el total se calcula al hacer la salida</div>
                        @endif
                        @if($this->precioNocheReserva <= 0)
                            <div style="font-size:10px; color:#dc2626; margin-top:4px; font-weight:700;">⚠️ Esta habitación no tiene precio configurado para {{ $resNumeroPersonas ?: 1 }} persona(s).</div>
                        @endif
                    </div>
                @endif

                <div style="margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <label style="font-size:11px; font-weight:700; color:#4b5563;">Nombre del huésped *</label>
                        <span style="display:flex; gap:8px;">
                            <button type="button" wire:click="abrirModalBuscarClienteHotel" style="border:none; background:none; color:#2563eb; font-size:11px; font-weight:700; cursor:pointer;">🔍 Buscar cliente</button>
                            <button type="button" wire:click="abrirModalCrearClienteHotel" style="border:none; background:none; color:#16a34a; font-size:11px; font-weight:700; cursor:pointer;">➕ Cliente nuevo</button>
                        </span>
                    </div>
                    <input wire:model="resHuespedNombre" type="text"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('resHuespedNombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Teléfono</label>
                        <input wire:model="resHuespedTelefono" type="text"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Documento</label>
                        <input wire:model="resHuespedDocumento" type="text"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                @if($resInmediato)
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:11px; color:#1e40af;">
                    ℹ️ El huésped se hospeda de una vez. Puedes facturar de inmediato, dejarlo pendiente para cobrarlo cuando se vaya, o registrar abajo lo que pague ahora (ej. la noche completa) y dejar la cuenta abierta por si consume algo más.
                </div>
                @endif

                @if(! $reservaId)
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; margin-bottom:12px;" x-data="{
                        formato(v) { const limpio = String(v || '').replace(/\D/g, ''); return limpio ? Number(limpio).toLocaleString('es-CO') : ''; },
                        limpiar(v) { return String(v || '').replace(/\D/g, ''); }
                    }">
                    <label style="font-size:11px; font-weight:700; color:#92400e; display:block; margin-bottom:6px;">💰 Abono (opcional)</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <input type="text" inputmode="numeric" placeholder="0"
                                value="{{ $resAbonoMonto !== '' ? number_format((float) $resAbonoMonto, 0, ',', '.') : '' }}"
                                x-on:input="$event.target.value = formato($event.target.value); $wire.set('resAbonoMonto', limpiar($event.target.value))"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <select wire:model="resAbonoMedioPago"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                            </select>
                        </div>
                    </div>
                    <div style="font-size:9px; color:#92400e; margin-top:4px;">Si registras un abono, se descuenta del total a cobrar en la salida y queda registrado como entrada de caja.</div>
                </div>
                @endif

                <div style="margin-bottom:16px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Observaciones</label>
                    <textarea wire:model="resObservaciones" rows="2"
                        style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:13px; box-sizing:border-box; resize:none;"></textarea>
                </div>

                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button wire:click="$set('modalReserva',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    <button wire:click="guardarReserva" wire:loading.attr="disabled"
                        style="border:none; background:{{ $resInmediato ? '#f59e0b' : '#16a34a' }}; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💾 {{ $resInmediato ? 'Registrar entrada' : 'Guardar reserva' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Ver reserva (informativo, desde el calendario) --}}
    @if($modalVerReserva)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1200; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto;">
            <div style="background:#334155; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">👁️ Detalle de la reserva</span>
                <button wire:click="$set('modalVerReserva',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            @if($this->reservaVista)
                @php $rv = $this->reservaVista; @endphp
                <div style="padding:18px; font-size:13px; color:#1f2937;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-weight:900; font-size:15px;">🚪 {{ $rv->habitacion->numero ?? '?' }}</span>
                        <span style="font-size:10px; font-weight:700; background:#e2e8f0; color:#334155; border-radius:99px; padding:2px 10px; text-transform:uppercase;">
                            {{ ['reservada'=>'Reservada','checkin'=>'Con entrada','checkout'=>'Facturada','cancelada'=>'Cancelada'][$rv->estado] ?? $rv->estado }}
                        </span>
                    </div>

                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Huésped</div>
                        <div style="font-weight:700;">{{ $rv->huesped_nombre }}</div>
                        <div style="color:#6b7280; font-size:12px;">
                            {{ $rv->huesped_telefono ?: 'Sin teléfono' }} · {{ $rv->huesped_documento ?: 'Sin documento' }}
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div>
                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Entrada</div>
                            <div>{{ $rv->fecha_checkin->format('d/m/Y') }}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Salida</div>
                            <div>{{ $rv->fecha_checkout?->format('d/m/Y') ?? 'Sin definir' }}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Personas</div>
                            <div>{{ $rv->numero_personas }}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Aire/Ventilador</div>
                            <div>
                                @if($rv->climatizacion === 'aire') ❄️ Aire
                                @elseif($rv->climatizacion === 'ventilador') 🌀 Ventilador
                                @else Ninguno
                                @endif
                            </div>
                        </div>
                    </div>

                    <div style="background:#f8fafc; border-radius:8px; padding:10px; margin-bottom:10px;">
                        <div style="display:flex; justify-content:space-between;">
                            <span>Hospedaje ({{ $rv->numero_noches }} noche(s) × ${{ number_format($rv->precio_noche, 0, ',', '.') }})</span>
                            <span style="font-weight:700;">${{ number_format($rv->total_estimado, 0, ',', '.') }}</span>
                        </div>
                        @if($rv->total_consumos > 0)
                        <div style="display:flex; justify-content:space-between; margin-top:4px;">
                            <span>Consumos</span>
                            <span style="font-weight:700;">${{ number_format($rv->total_consumos, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        @if($rv->abono_monto > 0)
                        <div style="display:flex; justify-content:space-between; margin-top:4px; color:#d97706;">
                            <span>Abono ({{ $rv->abono_medio_pago }})</span>
                            <span style="font-weight:700;">-${{ number_format($rv->abono_monto, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <div style="display:flex; justify-content:space-between; margin-top:6px; border-top:1px dashed #cbd5e1; padding-top:6px; font-weight:900;">
                            <span>Saldo</span>
                            <span>${{ number_format($rv->saldo_pendiente, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($rv->observaciones)
                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Observaciones</div>
                        <div>{{ $rv->observaciones }}</div>
                    </div>
                    @endif

                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button wire:click="$set('modalVerReserva',false)"
                            style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                            Cerrar
                        </button>
                        @if($rv->estado === 'reservada')
                        <button wire:click="editarDesdeVerReserva"
                            style="border:none; background:#2563eb; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                            ✏️ Editar
                        </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Modal Buscar cliente --}}
    @if($modalBuscarClienteHotel)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1300; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:420px; max-height:80vh; overflow-y:auto;">
            <div style="background:#2563eb; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">🔍 Buscar cliente</span>
                <button wire:click="$set('modalBuscarClienteHotel',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:16px;">
                <input wire:model.live.debounce.300ms="buscarClienteHotelTexto" type="text" placeholder="Nombre, teléfono o documento..."
                    style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box; margin-bottom:10px;">

                @if($this->resultadosClienteHotel->isEmpty())
                    <div style="text-align:center; color:#9ca3af; font-size:12px; padding:20px 0;">No se encontraron clientes.</div>
                @else
                    @if(trim($buscarClienteHotelTexto) === '')
                        <div style="font-size:10px; color:#9ca3af; margin-bottom:6px;">Clientes recientes. Escribe para filtrar.</div>
                    @endif
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        @foreach($this->resultadosClienteHotel as $actor)
                            <button type="button" wire:click="seleccionarClienteHotel({{ $actor->id }})"
                                style="text-align:left; border:1px solid #e5e7eb; background:#f8fafc; border-radius:8px; padding:8px 10px; cursor:pointer;">
                                <div style="font-size:12px; font-weight:700; color:#1f2937;">{{ $actor->nombre }}</div>
                                <div style="font-size:10px; color:#6b7280;">{{ $actor->identificacion ?: 'Sin documento' }} · {{ $actor->telefono ?: 'Sin teléfono' }}</div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Crear cliente (mismos campos que "Crear Cliente" del POS base) --}}
    @if($modalCrearClienteHotel)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1300; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto;">
            <div style="background:#16a34a; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">➕ Crear cliente</span>
                <button wire:click="$set('modalCrearClienteHotel',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                <div style="margin-bottom:14px;">
                    <h3 style="font-size:12px; font-weight:700; color:#4f46e5; margin-bottom:8px;">Datos personales</h3>
                    <div style="display:grid; grid-template-columns:60% 40%; gap:10px; margin-bottom:8px;">
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Tipo Documento</label>
                            <select wire:model="nuevoClienteHotel.tipo_documento_id"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione</option>
                                @foreach(\App\Models\TipoDocumento::all() as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->nombre }}</option>
                                @endforeach
                            </select>
                            @error('nuevoClienteHotel.tipo_documento_id') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Numero de documento</label>
                            <input type="text" wire:model.defer="nuevoClienteHotel.identificacion"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                            @error('nuevoClienteHotel.identificacion') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Nombre</label>
                        <input type="text" wire:model.defer="nuevoClienteHotel.nombre"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('nuevoClienteHotel.nombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Razon Social</label>
                        <input type="text" wire:model.defer="nuevoClienteHotel.razon_social"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <h3 style="font-size:12px; font-weight:700; color:#4f46e5; margin-bottom:8px;">Datos de contacto</h3>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Correo electronico</label>
                        <input type="email" wire:model.defer="nuevoClienteHotel.email"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('nuevoClienteHotel.email') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Telefono</label>
                            <input type="tel" wire:model.defer="nuevoClienteHotel.telefono"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                            @error('nuevoClienteHotel.telefono') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Direccion</label>
                            <input type="text" wire:model.defer="nuevoClienteHotel.direccion"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                            @error('nuevoClienteHotel.direccion') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <h3 style="font-size:12px; font-weight:700; color:#4f46e5; margin-bottom:8px;">Ubicacion</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Departamento</label>
                            <select wire:model.live="nuevoClienteHotel.departamento_id"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione...</option>
                                @foreach(\App\Models\Departamento::all() as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                @endforeach
                            </select>
                            @error('nuevoClienteHotel.departamento_id') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Ciudad</label>
                            <select wire:model.live="nuevoClienteHotel.ciudad_id"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione...</option>
                                @if(!empty($nuevoClienteHotel['departamento_id']))
                                    @foreach(\App\Models\Ciudad::where('departamento_id', $nuevoClienteHotel['departamento_id'])->get() as $ciu)
                                        <option value="{{ $ciu->id }}">{{ $ciu->nombre }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @error('nuevoClienteHotel.ciudad_id') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <h3 style="font-size:12px; font-weight:700; color:#4f46e5; margin-bottom:8px;">Datos tributarios</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Tipo de persona</label>
                            <select wire:model.defer="nuevoClienteHotel.tipo_persona"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione</option>
                                <option value="natural">Natural</option>
                                <option value="juridica">Juridica</option>
                            </select>
                            @error('nuevoClienteHotel.tipo_persona') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Regimen tributario</label>
                            <select wire:model.defer="nuevoClienteHotel.regimen_tributario"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione</option>
                                <option value="comun">Comun</option>
                                <option value="simplificado">Simplificado</option>
                                <option value="especial">Especial</option>
                                <option value="otro">Otro</option>
                            </select>
                            @error('nuevoClienteHotel.regimen_tributario') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; color:#4b5563; display:block; margin-bottom:4px;">Responsable de IVA?</label>
                            <select wire:model.defer="nuevoClienteHotel.responsable_iva"
                                style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                                <option value="">Seleccione</option>
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                            @error('nuevoClienteHotel.responsable_iva') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button wire:click="$set('modalCrearClienteHotel',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    <button wire:click="guardarClienteHotel" wire:loading.attr="disabled"
                        style="border:none; background:#16a34a; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💾 Guardar cliente
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('notify', (data) => {
            const d = Array.isArray(data) ? data[0] : data;
            if (d.type === 'success') Swal.fire({ icon: 'success', title: d.message, timer: 2000, showConfirmButton: false });
            else Swal.fire({ icon: d.type || 'info', title: d.message, confirmButtonColor: '#7c3aed' });
        });
    });
</script>
