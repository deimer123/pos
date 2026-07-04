<div style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div style="padding:10px 16px; background:#7c3aed; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-size:16px; font-weight:700; white-space:nowrap;">🏨 Hotel</span>

            <input wire:model.live.debounce.300ms="busqueda" type="text" placeholder="Buscar habitación..."
                style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; width:180px; outline:none; color:#1f2937;">

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
            <button wire:click="abrirNuevaHabitacion"
                style="border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; background:white; color:#7c3aed; white-space:nowrap;">
                ➕ Nueva habitación
            </button>
        </div>
    </div>

    {{-- Vista Habitaciones --}}
    @if($vistaActiva === 'habitaciones')
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @if($habitaciones->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🏨</div>
                <div style="margin-top:12px; font-size:15px; font-weight:600;">No hay habitaciones registradas.</div>
                <div style="font-size:12px; color:#cbd5e1; margin-top:6px;">Crea la primera con "➕ Nueva habitación".</div>
            </div>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px;">
            @foreach($habitaciones as $h)
                @php
                    $colores = [
                        'libre'     => ['bg'=>'#f0fdf4','border'=>'#86efac','badge'=>'#16a34a','text'=>'#14532d','icon'=>'🟢','label'=>'Libre'],
                        'reservada' => ['bg'=>'#fef3c7','border'=>'#fbbf24','badge'=>'#f59e0b','text'=>'#78350f','icon'=>'🟡','label'=>'Reservada'],
                        'ocupada'   => ['bg'=>'#fef2f2','border'=>'#fca5a5','badge'=>'#dc2626','text'=>'#7f1d1d','icon'=>'🔴','label'=>'Ocupada'],
                    ];
                    $c = $colores[$h->estado_actual] ?? $colores['libre'];
                    $r = $h->reserva_activa;
                @endphp
                <div style="background:{{ $c['bg'] }}; border:2px solid {{ $c['border'] }}; border-radius:12px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:10px; font-weight:700; background:{{ $c['badge'] }}; color:white; border-radius:99px; padding:2px 10px;">
                            {{ $c['icon'] }} {{ $c['label'] }}
                        </span>
                        <span style="font-size:16px; font-weight:900; color:{{ $c['text'] }};">🚪 {{ $h->numero }}</span>
                    </div>

                    <div style="font-size:11px; color:#374151; display:flex; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                        @if($h->camas_dobles > 0)<span>🛏️×{{ $h->camas_dobles }} doble</span>@endif
                        @if($h->camas_sencillas > 0)<span>🛏️×{{ $h->camas_sencillas }} sencilla</span>@endif
                        <span>👥 hasta {{ $h->capacidad_maxima }}</span>
                    </div>
                    <div style="font-size:11px; color:#6b7280; margin-bottom:6px;">
                        @if($h->tiene_aire)<span>❄️ Aire</span>@endif
                        @if($h->tiene_ventilador)<span> 🌀 Ventilador</span>@endif
                        @if(!$h->tiene_aire && !$h->tiene_ventilador)<span>Sin aire/ventilador</span>@endif
                    </div>
                    <div style="font-size:12px; font-weight:700; color:#7c3aed; margin-bottom:8px;">
                        ${{ number_format($h->precio_persona_noche, 0, ',', '.') }} / persona / noche
                    </div>

                    @if($r)
                    <div style="background:white; border-radius:8px; padding:8px; margin-bottom:8px; font-size:11px;">
                        <div style="font-weight:700; color:#1f2937;">👤 {{ $r->huesped_nombre }}</div>
                        <div style="color:#6b7280; margin-top:2px;">
                            {{ $r->fecha_checkin->format('d/m') }} → {{ $r->fecha_checkout->format('d/m') }}
                            · {{ $r->numero_personas }} pers. · {{ $r->numero_noches }} noche(s)
                        </div>
                        <div style="color:#16a34a; font-weight:700; margin-top:2px;">${{ number_format($r->total_estimado, 0, ',', '.') }}</div>
                    </div>
                    @endif

                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        @if(! $r)
                            <button wire:click="abrirNuevaReserva({{ $h->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#16a34a; color:white;">
                                📅 Reservar
                            </button>
                        @elseif($r->estado === 'reservada')
                            <button wire:click="confirmarCheckin({{ $r->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Check-in
                            </button>
                            <button wire:click="abrirEditarReserva({{ $r->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#eff6ff; color:#2563eb;">
                                ✏️ Editar
                            </button>
                            <button wire:click="cancelarReserva({{ $r->id }})"
                                onclick="return confirm('¿Cancelar esta reserva?')"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#fef2f2; color:#ef4444;">
                                ✕ Cancelar
                            </button>
                        @else
                            <button wire:click="irAFacturar({{ $r->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                                🧾 Check-out / Facturar
                            </button>
                        @endif
                        <button wire:click="abrirEditarHabitacion({{ $h->id }})"
                            style="border:none; border-radius:6px; padding:6px 8px; font-size:11px; font-weight:700; cursor:pointer; background:#f3f4f6; color:#374151;">
                            🔧
                        </button>
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
                        <th style="position:sticky; left:0; background:#f1f5f9; padding:8px; text-align:left; border-bottom:1px solid #e5e7eb; min-width:80px;">Habitación</th>
                        @php $diasAbrev = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']; @endphp
                        @foreach($diasCalendario as $dia)
                            <th style="padding:6px; text-align:center; border-bottom:1px solid #e5e7eb; min-width:56px; color:#6b7280;">
                                {{ $dia->format('d/m') }}<br><span style="font-weight:400;">{{ $diasAbrev[$dia->dayOfWeek] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($calendario as $fila)
                        <tr>
                            <td style="position:sticky; left:0; background:white; padding:8px; font-weight:700; border-bottom:1px solid #f1f5f9;">
                                🚪 {{ $fila['habitacion']->numero }}
                            </td>
                            @foreach($fila['celdas'] as $celda)
                                @php
                                    $res = $celda['reserva'];
                                    $bg = $res ? ($res->estado === 'checkin' ? '#fca5a5' : '#fde68a') : '#f0fdf4';
                                @endphp
                                <td title="{{ $res ? $res->huesped_nombre : 'Libre' }}"
                                    style="padding:6px; text-align:center; border-bottom:1px solid #f1f5f9; background:{{ $bg }}; cursor:{{ $res ? 'default' : 'pointer' }};"
                                    @if(! $res) wire:click="abrirNuevaReserva({{ $fila['habitacion']->id }})" @endif>
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
        <div style="display:flex; gap:14px; margin-top:10px; font-size:11px; color:#6b7280;">
            <span><span style="display:inline-block; width:10px; height:10px; background:#f0fdf4; border:1px solid #86efac; border-radius:2px; vertical-align:middle;"></span> Libre</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:#fde68a; border-radius:2px; vertical-align:middle;"></span> Reservada</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:#fca5a5; border-radius:2px; vertical-align:middle;"></span> Ocupada (check-in)</span>
        </div>
        @endif
    </div>
    @endif

    {{-- Modal Crear/Editar Habitación --}}
    @if($modalHabitacion)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1200; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:440px; max-height:90vh; overflow-y:auto;">
            <div style="background:#7c3aed; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">{{ $habitacionId ? '✏️ Editar habitación' : '➕ Nueva habitación' }}</span>
                <button wire:click="$set('modalHabitacion',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;" x-data="{
                    formato(v) { const limpio = String(v || '').replace(/\D/g, ''); return limpio ? Number(limpio).toLocaleString('es-CO') : ''; },
                    limpiar(v) { return String(v || '').replace(/\D/g, ''); }
                }">

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Número de habitación *</label>
                    <input wire:model="habNumero" type="text" placeholder="Ej: 101, 202-A..."
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('habNumero') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Camas dobles</label>
                        <input wire:model="habCamasDobles" type="number" min="0"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Camas sencillas</label>
                        <input wire:model="habCamasSencillas" type="number" min="0"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="display:flex; gap:16px; margin-bottom:12px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#4b5563; cursor:pointer;">
                        <input wire:model="habTieneAire" type="checkbox"> ❄️ Tiene aire
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#4b5563; cursor:pointer;">
                        <input wire:model="habTieneVentilador" type="checkbox"> 🌀 Tiene ventilador
                    </label>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Precio por persona / noche *</label>
                    <input type="text" inputmode="numeric" placeholder="0"
                        value="{{ $habPrecioPersonaNoche !== '' ? number_format((float) $habPrecioPersonaNoche, 0, ',', '.') : '' }}"
                        x-on:input="$event.target.value = formato($event.target.value); $wire.set('habPrecioPersonaNoche', limpiar($event.target.value))"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('habPrecioPersonaNoche') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Observaciones</label>
                    <textarea wire:model="habObservaciones" rows="2"
                        style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:13px; box-sizing:border-box; resize:none;"></textarea>
                </div>

                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button wire:click="$set('modalHabitacion',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    @if($habitacionId)
                    <button wire:click="eliminarHabitacion({{ $habitacionId }})"
                        onclick="return confirm('¿Eliminar esta habitación?')"
                        style="border:none; background:#fef2f2; color:#ef4444; border-radius:8px; padding:8px 18px; font-size:13px; font-weight:700; cursor:pointer;">
                        🗑️ Eliminar
                    </button>
                    @endif
                    <button wire:click="guardarHabitacion" wire:loading.attr="disabled"
                        style="border:none; background:#7c3aed; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💾 Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Nueva/Editar Reserva --}}
    @if($modalReserva)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1200; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto;">
            <div style="background:#16a34a; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">{{ $reservaId ? '✏️ Editar reserva' : '📅 Nueva reserva' }}</span>
                <button wire:click="$set('modalReserva',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Habitación *</label>
                    <select wire:model.live="resHabitacionId"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                        <option value="">Selecciona...</option>
                        @foreach($todasHabitaciones as $h)
                            <option value="{{ $h->id }}">🚪 {{ $h->numero }} · hasta {{ $h->capacidad_maxima }} pers. · ${{ number_format($h->precio_persona_noche, 0, ',', '.') }}/pers/noche</option>
                        @endforeach
                    </select>
                    @error('resHabitacionId') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Nombre del huésped *</label>
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

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Check-in *</label>
                        <input wire:model.live="resFechaCheckin" type="date"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Check-out *</label>
                        <input wire:model.live="resFechaCheckout" type="date"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('resFechaCheckout') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Personas *</label>
                        <input wire:model.live="resNumeroPersonas" type="number" min="1"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('resNumeroPersonas') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($habitacionSeleccionada)
                <div style="background:#f0fdf4; border-radius:8px; padding:12px; margin-bottom:12px; text-align:center;">
                    <div style="font-size:10px; color:#16a34a; text-transform:uppercase; font-weight:700;">Total estimado</div>
                    <div style="font-size:22px; font-weight:900; color:#16a34a;">${{ number_format($totalEstimadoReserva, 0, ',', '.') }}</div>
                    <div style="font-size:10px; color:#6b7280;">${{ number_format($habitacionSeleccionada->precio_persona_noche, 0, ',', '.') }} × {{ $resNumeroPersonas ?: 1 }} persona(s) × noche(s)</div>
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
                        style="border:none; background:#16a34a; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💾 Guardar reserva
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
