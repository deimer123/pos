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

    {{-- Vista Habitaciones --}}
    @if($vistaActiva === 'habitaciones')
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @if($habitaciones->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🏨</div>
                <div style="margin-top:12px; font-size:15px; font-weight:600;">No hay habitaciones registradas.</div>
                <div style="font-size:12px; color:#cbd5e1; margin-top:6px;">Créalas en <strong>Administración → Hotel → Habitaciones</strong>.</div>
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

                    @if($h->zona)
                    <div style="font-size:10px; color:#7c3aed; font-weight:700; margin-bottom:4px;">📍 {{ $h->zona }}</div>
                    @endif

                    <div style="font-size:11px; color:#374151; display:flex; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                        @if($h->camas_dobles > 0)<span>🛏️×{{ $h->camas_dobles }} doble</span>@endif
                        @if($h->camas_sencillas > 0)<span>🛏️×{{ $h->camas_sencillas }} sencilla</span>@endif
                        <span>👥 hasta {{ $h->capacidad_maxima }}</span>
                    </div>
                    <div style="font-size:11px; color:#6b7280; margin-bottom:6px;">
                        @if($h->tiene_aire)<span>❄️ Aire (+${{ number_format($h->recargo_aire, 0, ',', '.') }})</span>@endif
                        @if($h->tiene_ventilador)<span> 🌀 Ventilador (+${{ number_format($h->recargo_ventilador, 0, ',', '.') }})</span>@endif
                        @if(!$h->tiene_aire && !$h->tiene_ventilador)<span>Sin aire/ventilador</span>@endif
                    </div>
                    <div style="font-size:12px; font-weight:700; color:#7c3aed; margin-bottom:8px;">
                        Desde ${{ number_format($h->precio_desde, 0, ',', '.') }} / noche (1 persona, ya incluye aire/ventilador)
                    </div>

                    @if($r)
                    <div style="background:white; border-radius:8px; padding:8px; margin-bottom:8px; font-size:11px;">
                        <div style="font-weight:700; color:#1f2937;">👤 {{ $r->huesped_nombre }}</div>
                        <div style="color:#6b7280; margin-top:2px;">
                            {{ $r->fecha_checkin->format('d/m') }} → {{ $r->fecha_checkout?->format('d/m') ?? 'Sin definir' }}
                            · {{ $r->numero_personas }} pers. · {{ $r->numero_noches }} noche(s)
                        </div>
                        <div style="color:#16a34a; font-weight:700; margin-top:2px;">${{ number_format($r->total_estimado, 0, ',', '.') }}</div>
                        @if($r->abono_monto > 0)
                        <div style="color:#d97706; margin-top:2px;">Abono: ${{ number_format($r->abono_monto, 0, ',', '.') }} ({{ $r->abono_medio_pago }})</div>
                        @endif
                        @if($r->total_consumos > 0)
                        <div style="color:#7c3aed; margin-top:2px;">+ Consumos: ${{ number_format($r->total_consumos, 0, ',', '.') }}</div>
                        @endif
                        <div style="color:#1f2937; font-weight:900; margin-top:2px; border-top:1px dashed #e5e7eb; padding-top:2px;">Saldo: ${{ number_format($r->saldo_pendiente, 0, ',', '.') }}</div>
                    </div>
                    @endif

                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        @if(! $r)
                            <button wire:click="abrirNuevaReserva({{ $h->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#16a34a; color:white;">
                                📅 Reservar
                            </button>
                            <button wire:click="abrirNuevaReserva({{ $h->id }}, true)"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Entrada ahora
                            </button>
                        @elseif($r->estado === 'reservada')
                            <button wire:click="confirmarCheckin({{ $r->id }})"
                                style="flex:1; border:none; border-radius:6px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#f59e0b; color:white;">
                                🔑 Entrada
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
                                <td title="{{ $res ? $res->huesped_nombre : 'Libre' }}"
                                    style="padding:6px; text-align:center; border:1px solid #e2e8f0; background:{{ $bg }}; cursor:{{ $res ? 'default' : 'pointer' }};"
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
                        <div style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:0 10px; font-size:13px; box-sizing:border-box; background:#f8fafc; display:flex; align-items:center; justify-content:space-between;">
                            <span>🚪 {{ $hs->numero }}
                                @if($hs->tiene_aire) ❄️ @endif
                                @if($hs->tiene_ventilador) 🌀 @endif
                                · hasta {{ $hs->capacidad_maxima }} pers.
                            </span>
                            @unless($reservaId)
                            <button type="button" wire:click="cambiarHabitacionReserva" style="border:none; background:none; color:#2563eb; font-size:11px; font-weight:700; cursor:pointer;">Cambiar</button>
                            @endunless
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

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Entrada *</label>
                        <input wire:model.live="resFechaCheckin" type="date"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Salida</label>
                        <input wire:model.live="resFechaCheckout" type="date"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">Déjalo en blanco si no sabe cuándo se va.</div>
                        @error('resFechaCheckout') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Personas *</label>
                        <input wire:model.live="resNumeroPersonas" type="number" min="1"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('resNumeroPersonas') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($this->habitacionSeleccionada)
                <div style="background:#f0fdf4; border-radius:8px; padding:12px; margin-bottom:12px; text-align:center;">
                    @if($resFechaCheckout)
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
                    @elseif($this->habitacionSeleccionada->tiene_aire || $this->habitacionSeleccionada->tiene_ventilador)
                        @php
                            $hSel = $this->habitacionSeleccionada;
                            $baseNoche = $hSel->precioParaPersonas(max(1, (int) $resNumeroPersonas));
                        @endphp
                        <div style="font-size:10px; color:#6b7280; margin-top:6px; border-top:1px dashed #bbf7d0; padding-top:4px;">
                            Base: ${{ number_format($baseNoche, 0, ',', '.') }}
                            @if($hSel->tiene_aire)
                                &nbsp;+ ❄️ Aire: ${{ number_format($hSel->recargo_aire, 0, ',', '.') }}
                            @endif
                            @if($hSel->tiene_ventilador)
                                &nbsp;+ 🌀 Ventilador: ${{ number_format($hSel->recargo_ventilador, 0, ',', '.') }}
                            @endif
                        </div>
                    @endif
                </div>
                @endif

                @if($resInmediato)
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:11px; color:#1e40af;">
                    ℹ️ El huésped se hospeda de una vez, no necesita abono. Puedes facturar de inmediato o dejarlo pendiente para cobrarlo cuando se vaya.
                </div>
                @endif

                @if(! $reservaId && ! $resInmediato)
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

    {{-- Modal Crear cliente --}}
    @if($modalCrearClienteHotel)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1300; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:420px; max-height:80vh; overflow-y:auto;">
            <div style="background:#16a34a; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">➕ Cliente nuevo</span>
                <button wire:click="$set('modalCrearClienteHotel',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:16px;">
                <div style="margin-bottom:10px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Nombre *</label>
                    <input wire:model="nuevoClienteHotel.nombre" type="text"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('nuevoClienteHotel.nombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Documento</label>
                        <input wire:model="nuevoClienteHotel.identificacion" type="text"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                        @error('nuevoClienteHotel.identificacion') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Teléfono</label>
                        <input wire:model="nuevoClienteHotel.telefono" type="text"
                            style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Dirección</label>
                    <input wire:model="nuevoClienteHotel.direccion" type="text"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
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
