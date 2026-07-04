<div style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div class="taller-header" style="padding:10px 16px; background:#0f766e; color:white; flex-shrink:0;">

        <div class="taller-header-row taller-header-titulo" style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px; font-weight:700; white-space:nowrap;">🔧 Taller</span>

            {{-- Búsqueda --}}
            <input wire:model.live.debounce.300ms="busqueda" type="text" placeholder="Buscar placa o cliente..."
                class="taller-search"
                style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; width:190px; outline:none; color:#1f2937;">
        </div>

        <div class="taller-header-row taller-header-filtros" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:8px;">

            {{-- Rango de fechas --}}
            <div class="taller-dates" style="display:flex; align-items:center; gap:4px;">
                <input wire:model.live="fechaDesde" type="date"
                    style="border:none; border-radius:10px; padding:4px 8px; font-size:11px; outline:none; color:#1f2937; height:28px;">
                <span style="font-size:11px;">→</span>
                <input wire:model.live="fechaHasta" type="date"
                    style="border:none; border-radius:10px; padding:4px 8px; font-size:11px; outline:none; color:#1f2937; height:28px;">
                @if($fechaDesde || $fechaHasta)
                <button wire:click="limpiarFechas"
                    style="border:none; border-radius:99px; background:rgba(255,255,255,.25); color:white; font-size:14px; width:24px; height:24px; cursor:pointer; padding:0; line-height:1; flex-shrink:0;">×</button>
                @endif
            </div>

            {{-- Separador --}}
            <span class="taller-separador" style="width:1px; height:20px; background:rgba(255,255,255,.3); display:inline-block;"></span>

            {{-- Filtros estado --}}
            <div class="taller-estado-group" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
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
            </div>

            {{-- Separador --}}
            <span class="taller-separador" style="width:1px; height:20px; background:rgba(255,255,255,.3); display:inline-block;"></span>

            <div class="taller-extra-group" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                {{-- Reporte PDF del listado actual --}}
                <a href="{{ route('taller.reporte.pdf', ['estado' => $filtroEstado ?: 'todos', 'desde' => $fechaDesde, 'hasta' => $fechaHasta, 'busqueda' => $busqueda]) }}"
                   target="_blank"
                   style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; background:rgba(255,255,255,.2); color:white; text-decoration:none; white-space:nowrap;">
                    📄 Reporte PDF
                </a>

                {{-- Tab Mecánicos --}}
                <button wire:click="$set('vistaActiva', @if($vistaActiva === 'mecanicos')'ordenes'@else'mecanicos'@endif)"
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;
                        background:{{ $vistaActiva === 'mecanicos' ? 'white' : 'rgba(255,255,255,.2)' }};
                        color:{{ $vistaActiva === 'mecanicos' ? '#0f766e' : 'white' }};">
                    👨‍🔧 Mecánicos
                </button>
            </div>
        </div>
    </div>

    {{-- Vista Mecánicos --}}
    @if($vistaActiva === 'mecanicos')
    <div style="flex:1; overflow-y:auto; padding:16px;">
        <div style="max-width:900px; margin:0 auto;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                <h2 style="font-size:16px; font-weight:700; color:#0f766e; margin:0;">👨‍🔧 Mecánicos y Liquidaciones</h2>
                <button wire:click="abrirCajaMecanicos"
                    style="border:none; border-radius:8px; padding:8px 14px; font-size:12px; font-weight:700; cursor:pointer; background:#7c3aed; color:white; white-space:nowrap;">
                    🧾 Cerrar Caja de Mecánicos
                </button>
            </div>

            {{-- Resumen combinado de todos los mecánicos --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-bottom:16px;">
                <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:12px 14px;">
                    <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Servicios pendientes</div>
                    <div style="font-size:18px; font-weight:900; color:#1f2937; margin-top:2px;">${{ number_format($resumenMecanicos['total_pendiente'], 0, ',', '.') }}</div>
                </div>
                <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:12px 14px;">
                    <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Falta por liquidar</div>
                    <div style="font-size:18px; font-weight:900; color:#dc2626; margin-top:2px;">${{ number_format($resumenMecanicos['a_liquidar'], 0, ',', '.') }}</div>
                </div>
                <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:12px 14px;">
                    <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Préstamos pendientes</div>
                    <div style="font-size:18px; font-weight:900; color:#d97706; margin-top:2px;">${{ number_format($resumenMecanicos['prestamos_pendientes'], 0, ',', '.') }}</div>
                </div>
                <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:12px 14px;">
                    <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Neto a liquidar</div>
                    <div style="font-size:18px; font-weight:900; color:#7c3aed; margin-top:2px;">${{ number_format($resumenMecanicos['a_liquidar_neto'], 0, ',', '.') }}</div>
                </div>
                <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; padding:12px 14px;">
                    <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:700;">Ganancia empresa (pendiente)</div>
                    <div style="font-size:18px; font-weight:900; color:#16a34a; margin-top:2px;">${{ number_format($resumenMecanicos['ganancia_pendiente'], 0, ',', '.') }}</div>
                </div>
            </div>

            {{-- Servicios a Terceros: independientes de los mecanicos propios --}}
            <div style="background:white; border-radius:12px; border:1px solid #fde68a; margin-bottom:16px; overflow:hidden;">
                <div style="background:#fffbeb; padding:12px 16px; display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:13px; font-weight:700; color:#d97706;">🤝 Servicios a Terceros</span>
                    <button wire:click="abrirNuevoServicioTercero"
                        style="border:none; border-radius:8px; padding:5px 12px; font-size:11px; font-weight:700; cursor:pointer; background:#d97706; color:white;">
                        ➕ Nuevo servicio a tercero
                    </button>
                </div>
                <div style="padding:12px 16px;">
                    @php $serviciosTerceros = $this->serviciosTerceros(); @endphp
                    @if($serviciosTerceros->isEmpty())
                        <div style="text-align:center; padding:12px; color:#94a3b8; font-size:12px;">
                            No hay servicios a terceros. Crea el primero con "➕ Nuevo servicio a tercero".
                        </div>
                    @else
                        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px;">
                            @foreach($serviciosTerceros as $svc)
                            <div style="background:white; border:1px solid #fde68a; border-radius:8px; padding:10px;">
                                <div style="font-weight:700; font-size:13px; color:#1f2937; margin-bottom:4px;">{{ $svc->nombre }}</div>
                                <div style="font-size:12px; color:#16a34a; font-weight:700;">${{ number_format($svc->precio_venta1, 0, ',', '.') }}</div>
                                <div style="font-size:10px; color:#f59e0b; margin-top:2px;">🤝 Tercero: {{ $svc->tercero_nombre }}</div>
                                <div style="font-size:10px; color:#6b7280; margin-top:2px;">
                                    🏢 Empresa: {{ $svc->porcentaje_empresa ?? 0 }}%
                                    · 🤝 Tercero: {{ 100 - ($svc->porcentaje_empresa ?? 0) }}%
                                </div>
                                <div style="display:flex; gap:6px; margin-top:8px;">
                                    <button wire:click="abrirEditarServicio({{ $svc->id_producto ?? $svc->id }})"
                                        style="flex:1; border:none; border-radius:6px; padding:4px; font-size:10px; font-weight:700; cursor:pointer; background:#fffbeb; color:#d97706;">
                                        ✏️ Editar
                                    </button>
                                    <button wire:click="eliminarServicio({{ $svc->id_producto ?? $svc->id }})"
                                        onclick="return confirm('¿Eliminar este servicio?')"
                                        style="flex:1; border:none; border-radius:6px; padding:4px; font-size:10px; font-weight:700; cursor:pointer; background:#fef2f2; color:#ef4444;">
                                        🗑️ Eliminar
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($mecanicos->isEmpty())
                <div style="text-align:center; padding:40px; color:#94a3b8; background:white; border-radius:12px;">
                    <div style="font-size:40px;">👨‍🔧</div>
                    <div style="margin-top:8px;">No hay mecánicos registrados. Créalos en <strong>Administración → Taller → Mecánicos</strong>.</div>
                </div>
            @else
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @foreach($mecanicos as $mec)
                    <div style="background:white; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden;">

                        {{-- Fila principal del mecánico --}}
                        <div style="display:flex; align-items:stretch; gap:0;">

                            {{-- Info mecánico --}}
                            <div style="flex:1; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                                <div style="font-size:30px;">🔧</div>
                                <div style="flex:1;">
                                    <div style="font-weight:700; font-size:15px; color:#1f2937;">{{ $mec->nombre }}</div>
                                    @if($mec->cedula)<span style="font-size:11px; color:#6b7280; margin-right:8px;">CC: {{ $mec->cedula }}</span>@endif
                                    @if($mec->telefono)<span style="font-size:11px; color:#6b7280;">📞 {{ $mec->telefono }}</span>@endif
                                </div>
                                {{-- Pendiente --}}
                                <div style="text-align:right;">
                                    <div style="font-size:10px; color:#6b7280;">Neto a liquidar</div>
                                    <div style="font-size:18px; font-weight:900; color:{{ $mec->monto_neto < 0 ? '#dc2626' : '#16a34a' }};">${{ number_format($mec->monto_neto, 0, ',', '.') }}</div>
                                    <div style="font-size:10px; color:#9ca3af;">{{ $mec->servicios_pending }} serv.</div>
                                    @if($mec->prestamos_pendientes > 0)
                                    <div style="font-size:10px; color:#dc2626; margin-top:2px;">💸 Préstamo: ${{ number_format($mec->prestamos_pendientes, 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            </div>

                            {{-- Acciones --}}
                            <div style="display:flex; flex-direction:column; border-left:1px solid #f3f4f6;">
                                <button wire:click="toggleServicios({{ $mec->id }})"
                                    style="flex:1; border:none; border-bottom:1px solid #f3f4f6; padding:8px 16px; font-size:11px; font-weight:700; cursor:pointer;
                                        background:{{ $svcExpandMecanico === $mec->id ? '#eff6ff' : 'white' }};
                                        color:{{ $svcExpandMecanico === $mec->id ? '#2563eb' : '#374151' }}; white-space:nowrap;">
                                    🛠️ Servicios
                                </button>
                                <button wire:click="abrirHistorialMecanico({{ $mec->id }})"
                                    style="flex:1; border:none; border-bottom:1px solid #f3f4f6; padding:8px 16px; font-size:11px; font-weight:700; cursor:pointer; background:white; color:#7c3aed; white-space:nowrap;">
                                    📊 Historial
                                </button>
                                <button wire:click="abrirPrestamo({{ $mec->id }})"
                                    style="flex:1; border:none; border-bottom:1px solid #f3f4f6; padding:8px 16px; font-size:11px; font-weight:700; cursor:pointer; background:white; color:#d97706; white-space:nowrap;">
                                    💸 Préstamo
                                </button>
                                @if($mec->servicios_pending > 0)
                                <button wire:click="abrirLiquidacion({{ $mec->id }})"
                                    style="flex:1; border:none; padding:8px 16px; font-size:11px; font-weight:700; cursor:pointer; background:#0f766e; color:white; white-space:nowrap;">
                                    💰 Liquidar
                                </button>
                                @else
                                <div style="flex:1; padding:8px 16px; font-size:10px; color:#94a3b8; text-align:center; display:flex; align-items:center; white-space:nowrap;">Sin pendientes</div>
                                @endif
                            </div>
                        </div>

                        {{-- Panel de servicios expandido --}}
                        @if($svcExpandMecanico === $mec->id)
                        <div style="border-top:2px solid #eff6ff; background:#f8fafc; padding:12px 16px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                                <span style="font-size:12px; font-weight:700; color:#2563eb;">🛠️ Servicios asignados a {{ $mec->nombre }}</span>
                                <button wire:click="abrirNuevoServicio({{ $mec->id }})"
                                    style="border:none; border-radius:8px; padding:5px 12px; font-size:11px; font-weight:700; cursor:pointer; background:#2563eb; color:white;">
                                    ➕ Nuevo servicio
                                </button>
                            </div>

                            @php $serviciosMec = $this->serviciosDelMecanico($mec->id); @endphp
                            @if($serviciosMec->isEmpty())
                                <div style="text-align:center; padding:16px; color:#94a3b8; font-size:12px;">
                                    No hay servicios asignados. Crea el primero con "➕ Nuevo servicio".
                                </div>
                            @else
                                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px;">
                                    @foreach($serviciosMec as $svc)
                                    <div style="background:white; border:1px solid #dbeafe; border-radius:8px; padding:10px;">
                                        <div style="font-weight:700; font-size:13px; color:#1f2937; margin-bottom:4px;">{{ $svc->nombre }}</div>
                                        <div style="font-size:12px; color:#16a34a; font-weight:700;">${{ number_format($svc->precio_venta1, 0, ',', '.') }}</div>
                                        @if($svc->tipo_servicio === 'tercero')
                                            <div style="font-size:10px; color:#f59e0b; margin-top:2px;">🤝 Tercero: {{ $svc->tercero_nombre }}</div>
                                        @else
                                            <div style="font-size:10px; color:#6b7280; margin-top:2px;">
                                                🏢 Empresa: {{ $svc->porcentaje_empresa ?? 0 }}%
                                                · 🔧 Mecánico: {{ 100 - ($svc->porcentaje_empresa ?? 0) }}%
                                            </div>
                                        @endif
                                        <div style="display:flex; gap:6px; margin-top:8px;">
                                            <button wire:click="abrirEditarServicio({{ $svc->id_producto ?? $svc->id }})"
                                                style="flex:1; border:none; border-radius:6px; padding:4px; font-size:10px; font-weight:700; cursor:pointer; background:#eff6ff; color:#2563eb;">
                                                ✏️ Editar
                                            </button>
                                            <button wire:click="eliminarServicio({{ $svc->id_producto ?? $svc->id }})"
                                                onclick="return confirm('¿Eliminar este servicio?')"
                                                style="flex:1; border:none; border-radius:6px; padding:4px; font-size:10px; font-weight:700; cursor:pointer; background:#fef2f2; color:#ef4444;">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @endif

                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Grid de órdenes --}}
    @if($vistaActiva === 'ordenes')
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
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px;">
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
                <div style="background:{{ $c['bg'] }}; border:2px solid {{ $c['border'] }}; border-radius:12px; padding:10px; position:relative;">

                    {{-- Badge estado + número --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:10px; font-weight:700; background:{{ $c['badge'] }}; color:white; border-radius:99px; padding:2px 10px;">
                            {{ $c['icon'] }} {{ $c['label'] }}
                        </span>
                        <span style="font-size:12px; font-weight:800; color:{{ $c['text'] }};">
                            # {{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    {{-- Vehículo --}}
                    <div style="font-size:18px; font-weight:900; color:#0f766e; letter-spacing:.05em;">{{ $orden->placa }}</div>
                    <div style="font-size:11px; color:#374151; font-weight:600; margin-top:1px;">
                        {{ trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '')) ?: '—' }}
                    </div>

                    {{-- Cliente --}}
                    <div style="margin-top:5px; font-size:12px; color:#1f2937; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        👤 {{ $orden->cliente_nombre }}
                    </div>

                    {{-- Total --}}
                    <div style="margin-top:5px; font-size:12px; font-weight:800; color:#0f766e;">
                        💰 Total: ${{ number_format($totalRep, 0, ',', '.') }}
                    </div>

                    {{-- Fecha --}}
                    <div style="font-size:9px; color:#9ca3af; margin-top:4px;">
                        {{ $orden->created_at->format('d/m/Y h:i A') }}
                        @if($orden->entregado_at)
                            · Cobrada {{ \Carbon\Carbon::parse($orden->entregado_at)->format('d/m/Y h:i A') }}
                        @endif
                    </div>

                    {{-- Detalle expandible: diagnóstico, nota de trabajo y repuestos --}}
                    <div x-data="{ abierto: false, nota: @js($orden->nota_trabajo ?? '') }" style="margin-top:6px;">
                        <button type="button" @click="abierto = !abierto"
                            style="width:100%; border:none; background:rgba(0,0,0,.05); color:#374151; border-radius:6px; padding:4px 8px; font-size:10px; font-weight:700; cursor:pointer; text-align:left;">
                            <span x-text="abierto ? '▾ Ocultar detalle' : '▸ Ver detalle / nota de trabajo'"></span>
                        </button>
                        <div x-show="abierto" style="margin-top:6px;">
                            @if($orden->diagnostico)
                            <div style="font-size:11px; color:#4b5563; background:rgba(0,0,0,.04); border-radius:8px; padding:6px 8px; line-height:1.4; margin-bottom:4px;">
                                📋 {{ $orden->diagnostico }}
                            </div>
                            @endif

                            @if($orden->observaciones)
                            <div style="font-size:10px; color:#6b7280; font-style:italic; padding:0 2px; margin-bottom:4px;">
                                {{ $orden->observaciones }}
                            </div>
                            @endif

                            <label style="font-size:9px; font-weight:700; color:#0f766e; text-transform:uppercase; display:block; margin-bottom:2px;">📝 Nota de trabajo realizado</label>
                            <textarea x-model="nota" @blur="$wire.guardarNotaTrabajo({{ $orden->id }}, nota)"
                                placeholder="Describe lo que se le hizo a la moto/carro durante la orden..."
                                rows="2"
                                style="width:100%; border:1px solid #99f6e4; border-radius:8px; padding:5px 7px; font-size:11px; box-sizing:border-box; resize:vertical; background:#f0fdfa; margin-bottom:6px;"></textarea>

                            @if($orden->repuestos->isNotEmpty())
                            <div style="font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:3px;">Repuestos / Servicios</div>
                            @foreach($orden->repuestos as $rep)
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#374151; padding:1px 0;">
                                <span>🔩 {{ $rep->descripcion }} <span style="color:#9ca3af;">× {{ $rep->cantidad }}</span></span>
                                <span style="font-weight:700; color:#0f766e;">${{ number_format($rep->subtotal, 0, ',', '.') }}</span>
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div style="display:flex; gap:4px; margin-top:8px; flex-wrap:wrap;">
                        @if(!$esCobrada)
                        <button wire:click="abrirOrden({{ $orden->id }})"
                            style="flex:1; border:none; border-radius:8px; padding:6px 4px; font-size:11px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                            🛒 POS
                        </button>
                        @if($orden->estado === 'pendiente')
                        <button wire:click="cambiarEstado({{ $orden->id }},'en_proceso')"
                            style="flex:1; border:none; border-radius:8px; padding:6px 4px; font-size:11px; font-weight:700; cursor:pointer; background:#3b82f6; color:white;">
                            🔧 Iniciar
                        </button>
                        @endif
                        @endif

                        {{-- PDF de esta orden (incluye diagnóstico, nota y repuestos completos) --}}
                        <a href="{{ route('taller.orden.pdf', $orden->id) }}" target="_blank"
                           style="flex:1; border:none; border-radius:8px; padding:6px 4px; font-size:11px; font-weight:700; cursor:pointer; background:#7c3aed; color:white; text-decoration:none; text-align:center;">
                            📄 PDF
                        </a>

                        @if($esCobrada && $orden->factura_id)
                        <a href="{{ route('factura.ver', $orden->factura_id) }}" target="_blank"
                           style="flex:1; border:none; border-radius:8px; padding:6px 4px; font-size:11px; font-weight:700; cursor:pointer; background:#16a34a; color:white; text-decoration:none; text-align:center;">
                            🧾 Factura
                        </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif {{-- fin @if($vistaActiva === 'ordenes') --}}

    {{-- Modal Crear/Editar Servicio --}}
    @if($modalServicio)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1200; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto;">
            <div style="background:#2563eb; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">{{ $servicioId ? '✏️ Editar servicio' : '➕ Nuevo servicio' }}</span>
                <button wire:click="$set('modalServicio',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;" x-data="{
                    formato(v) { const limpio = String(v || '').replace(/\D/g, ''); return limpio ? Number(limpio).toLocaleString('es-CO') : ''; },
                    limpiar(v) { return String(v || '').replace(/\D/g, ''); }
                }">

                {{-- Nombre --}}
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Nombre del servicio *</label>
                    <input wire:model="svcNombre" type="text" placeholder="Ej: Cambio de aceite, Afinación..."
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('svcNombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                {{-- Precio --}}
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Precio de venta *</label>
                    <input type="text" inputmode="numeric" placeholder="0"
                        value="{{ $svcPrecio !== '' ? number_format((float) $svcPrecio, 0, ',', '.') : '' }}"
                        x-on:input="$event.target.value = formato($event.target.value); $wire.set('svcPrecio', limpiar($event.target.value))"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                    @error('svcPrecio') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>

                {{-- Tipo de servicio --}}
                @if(! $svcBloquearTipo)
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Tipo de servicio *</label>
                    <div style="display:flex; gap:8px;">
                        <button type="button" wire:click="$set('svcTipoServicio','propio')"
                            style="flex:1; border:2px solid {{ $svcTipoServicio === 'propio' ? '#2563eb' : '#e5e7eb' }}; border-radius:8px; padding:8px; font-size:12px; font-weight:700; cursor:pointer;
                                background:{{ $svcTipoServicio === 'propio' ? '#eff6ff' : 'white' }}; color:{{ $svcTipoServicio === 'propio' ? '#2563eb' : '#6b7280' }};">
                            🔧 Propio (mecánico asignado)
                        </button>
                        <button type="button" wire:click="$set('svcTipoServicio','tercero')"
                            style="flex:1; border:2px solid {{ $svcTipoServicio === 'tercero' ? '#f59e0b' : '#e5e7eb' }}; border-radius:8px; padding:8px; font-size:12px; font-weight:700; cursor:pointer;
                                background:{{ $svcTipoServicio === 'tercero' ? '#fffbeb' : 'white' }}; color:{{ $svcTipoServicio === 'tercero' ? '#d97706' : '#6b7280' }};">
                            🤝 Tercero (externo)
                        </button>
                    </div>
                </div>
                @elseif($svcTipoServicio === 'tercero')
                <div style="margin-bottom:12px; background:#fffbeb; border-radius:8px; padding:8px 12px; font-size:11px; font-weight:700; color:#d97706;">
                    🤝 Servicio a tercero (externo)
                </div>
                @else
                <div style="margin-bottom:12px; background:#eff6ff; border-radius:8px; padding:8px 12px; font-size:11px; font-weight:700; color:#2563eb;">
                    🔧 Servicio propio de este mecánico
                </div>
                @endif

                @if($svcTipoServicio === 'propio')
                {{-- % Empresa (propio) --}}
                <div style="margin-bottom:12px; background:#eff6ff; border-radius:8px; padding:12px;">
                    <label style="font-size:11px; font-weight:700; color:#2563eb; display:block; margin-bottom:4px;">% que queda para la empresa</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input wire:model.live="svcPctEmpresa" type="range" min="0" max="100" step="5"
                            style="flex:1; accent-color:#2563eb;">
                        <span style="font-size:16px; font-weight:900; color:#2563eb; min-width:40px; text-align:right;">{{ $svcPctEmpresa }}%</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#6b7280; margin-top:4px;">
                        <span>🏢 Empresa: {{ $svcPctEmpresa }}%</span>
                        <span>🔧 Mecánico: {{ 100 - (int)$svcPctEmpresa }}%</span>
                    </div>
                    @if($svcPrecio)
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; margin-top:6px; padding-top:6px; border-top:1px solid #bfdbfe;">
                        <span style="color:#2563eb;">🏢 ${{ number_format((float)$svcPrecio * (int)$svcPctEmpresa / 100, 0, ',', '.') }}</span>
                        <span style="color:#16a34a;">🔧 ${{ number_format((float)$svcPrecio * (100 - (int)$svcPctEmpresa) / 100, 0, ',', '.') }}</span>
                    </div>
                    @endif
                </div>
                @else
                {{-- Nombre del tercero --}}
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Nombre del tercero / proveedor</label>
                    <input wire:model="svcTerceroNombre" type="text" placeholder="Nombre de quien presta el servicio..."
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                </div>

                {{-- Precio de costo (lo que cobra el tercero) --}}
                <div style="margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Precio de costo (lo que cobra el tercero)</label>
                    <input type="text" inputmode="numeric" placeholder="0"
                        value="{{ $svcCosto !== '' ? number_format((float) $svcCosto, 0, ',', '.') : '' }}"
                        x-on:input="$event.target.value = formato($event.target.value); $wire.set('svcCosto', limpiar($event.target.value))"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                </div>

                {{-- % Empresa para tercero: calculado a partir de costo y venta --}}
                <div style="margin-bottom:12px; background:#fffbeb; border-radius:8px; padding:12px;">
                    <label style="font-size:11px; font-weight:700; color:#d97706; display:block; margin-bottom:4px;">% que queda para la empresa (calculado)</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input wire:model="svcPctEmpresa" type="range" min="0" max="100" step="1" disabled
                            style="flex:1; accent-color:#f59e0b;">
                        <span style="font-size:16px; font-weight:900; color:#d97706; min-width:40px; text-align:right;">{{ $svcPctEmpresa }}%</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#6b7280; margin-top:4px;">
                        <span>🏢 Empresa: {{ $svcPctEmpresa }}%</span>
                        <span>🤝 Tercero recibe: {{ 100 - (float)$svcPctEmpresa }}%</span>
                    </div>
                    @if($svcPrecio)
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; margin-top:6px; padding-top:6px; border-top:1px solid #fde68a;">
                        <span style="color:#d97706;">🏢 ${{ number_format(((float)$svcPrecio - (float)$svcCosto), 0, ',', '.') }}</span>
                        <span style="color:#16a34a;">🤝 ${{ number_format((float)$svcCosto, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    <div style="font-size:10px; color:#9ca3af; margin-top:4px;">Se calcula solo: (Venta − Costo) / Venta.</div>
                </div>
                @endif

                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
                    <button wire:click="$set('modalServicio',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    <button wire:click="guardarServicio" wire:loading.attr="disabled"
                        style="border:none; background:#2563eb; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💾 Guardar servicio
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Liquidación Mecánico --}}
    @if($modalLiquidacion)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1100; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:640px; max-height:90vh; overflow-y:auto;">
            <div style="background:#0f766e; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">💰 Liquidar mecánico</span>
                <button wire:click="$set('modalLiquidacion',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                {{-- Período --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563;">Desde</label>
                        <input wire:model.live="liqFechaDesde" type="date"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563;">Hasta</label>
                        <input wire:model.live="liqFechaHasta" type="date"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>

                {{-- Resumen servicios --}}
                @if(empty($liqServicios))
                    <div style="text-align:center; padding:20px; color:#94a3b8; background:#f8fafc; border-radius:8px; margin-bottom:14px;">
                        Sin servicios pendientes en el período seleccionado.
                    </div>
                @else
                <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:14px; max-height:220px; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                        <thead style="position:sticky;top:0;background:#f1f5f9;">
                            <tr>
                                <th style="padding:6px 8px; text-align:left; color:#6b7280;">Fecha</th>
                                <th style="padding:6px 8px; text-align:right; color:#6b7280;">Total cobrado</th>
                                <th style="padding:6px 8px; text-align:right; color:#6b7280;">% Mecánico</th>
                                <th style="padding:6px 8px; text-align:right; color:#6b7280;">Al mecánico</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($liqServicios as $svc)
                            <tr style="border-top:1px solid #f1f5f9;">
                                <td style="padding:5px 8px;">{{ $svc['fecha'] }}</td>
                                <td style="padding:5px 8px; text-align:right;">${{ number_format($svc['subtotal'], 0, ',', '.') }}</td>
                                <td style="padding:5px 8px; text-align:right;">{{ 100 - $svc['pct_empresa'] }}%</td>
                                <td style="padding:5px 8px; text-align:right; font-weight:700; color:#16a34a;">${{ number_format($svc['monto_mecanico'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="background:#f0fdf4; border-radius:8px; padding:12px; margin-bottom:14px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; text-align:center;">
                    <div>
                        <div style="font-size:10px; color:#6b7280;">Total cobrado</div>
                        <div style="font-size:16px; font-weight:700; color:#374151;">${{ number_format($liqTotalServicios, 0, ',', '.') }}</div>
                    </div>
                    <div>
                        <div style="font-size:10px; color:#6b7280;">% promedio mecánico</div>
                        <div style="font-size:16px; font-weight:700; color:#2563eb;">{{ number_format($liqPorcentajeMecanico, 1, ',', '.') }}%</div>
                    </div>
                    <div>
                        <div style="font-size:10px; color:#6b7280;">A pagar al mecánico</div>
                        <div style="font-size:20px; font-weight:900; color:#16a34a;">${{ number_format($liqMontoMecanico, 0, ',', '.') }}</div>
                    </div>
                </div>

                @if($liqPrestamosPendientes > 0)
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; margin-bottom:14px; display:grid; grid-template-columns:1fr 1fr; gap:8px; text-align:center;">
                    <div>
                        <div style="font-size:10px; color:#991b1b;">💸 Préstamo pendiente (se descuenta)</div>
                        <div style="font-size:16px; font-weight:700; color:#dc2626;">- ${{ number_format($liqPrestamosPendientes, 0, ',', '.') }}</div>
                    </div>
                    <div>
                        <div style="font-size:10px; color:#991b1b;">Neto a pagar al mecánico</div>
                        <div style="font-size:20px; font-weight:900; color:{{ $liqMontoNeto < 0 ? '#dc2626' : '#16a34a' }};">${{ number_format($liqMontoNeto, 0, ',', '.') }}</div>
                    </div>
                </div>
                @endif
                @endif

                {{-- Medio de pago y notas --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563;">Medio de pago</label>
                        <select wire:model="liqMedioPago"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563;">Notas (opcional)</label>
                        <input wire:model="liqNotas" type="text" placeholder="Semana 1, enero, etc."
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>

                {{-- Botones --}}
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button wire:click="$set('modalLiquidacion',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    @if(!empty($liqServicios))
                    <button wire:click="confirmarLiquidacion" wire:loading.attr="disabled"
                        style="border:none; background:#16a34a; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💰 Confirmar liquidación (${{ number_format($liqPrestamosPendientes > 0 ? $liqMontoNeto : $liqMontoMecanico, 0, ',', '.') }})
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Historial de servicios por mecánico --}}
    @if($modalHistorialMecanico)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1150; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto;">
            <div style="background:#7c3aed; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">📊 Historial de servicios</span>
                <button wire:click="$set('modalHistorialMecanico',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                {{-- Rango de fechas --}}
                <div style="display:flex; gap:10px; align-items:end; margin-bottom:14px; flex-wrap:wrap;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Desde</label>
                        <input wire:model.live="histDesde" type="date"
                            style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Hasta</label>
                        <input wire:model.live="histHasta" type="date"
                            style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                    </div>
                </div>

                @php $historial = $this->historialMecanico(); @endphp

                @if($historial->isEmpty())
                    <div style="text-align:center; padding:24px; color:#94a3b8; font-size:12px;">
                        No hay servicios en este rango de fechas.
                    </div>
                @else
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach($historial as $svc)
                        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
                            <div style="min-width:0;">
                                <div style="font-weight:700; font-size:13px; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    {{ $svc->descripcion }}
                                </div>
                                <div style="font-size:10px; color:#6b7280; margin-top:2px;">
                                    {{ $svc->fecha }} · {{ $svc->numero_visual }}
                                </div>
                            </div>
                            <div style="text-align:right; flex-shrink:0;">
                                <div style="font-size:13px; font-weight:700; color:#16a34a;">${{ number_format($svc->monto_mecanico, 0, ',', '.') }}</div>
                                <div style="font-size:9px; color:#9ca3af;">de ${{ number_format($svc->subtotal, 0, ',', '.') }}</div>
                            </div>
                            <div style="flex-shrink:0;">
                                @if($svc->liquidado)
                                    <span style="font-size:10px; font-weight:700; background:#dcfce7; color:#16a34a; border-radius:99px; padding:3px 10px; white-space:nowrap;">
                                        ✅ Liquidado {{ $svc->fecha_pago ? \Carbon\Carbon::parse($svc->fecha_pago)->format('d/m/Y') : '' }}
                                    </span>
                                @else
                                    <span style="font-size:10px; font-weight:700; background:#fef3c7; color:#92400e; border-radius:99px; padding:3px 10px; white-space:nowrap;">
                                        ⏳ Pendiente
                                    </span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>
    </div>
    @endif

    {{-- Modal Cerrar Caja de Mecánicos (servicios propios + terceros, aparte del POS) --}}
    @if($modalCajaMecanicos)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1150; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto;">
            <div style="background:#7c3aed; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">🧾 Cerrar Caja de Mecánicos</span>
                <button wire:click="$set('modalCajaMecanicos',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">

                <div style="display:flex; gap:10px; align-items:end; margin-bottom:14px; flex-wrap:wrap;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Desde</label>
                        <input wire:model.live="cajaMecDesde" type="date"
                            style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Hasta</label>
                        <input wire:model.live="cajaMecHasta" type="date"
                            style="height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                    </div>
                </div>

                @php $resumenCajaMec = $this->calcularCajaMecanicos(); @endphp

                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:12px; font-size:12px;">
                    <div style="font-size:10px; color:#7c3aed; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Servicios cobrados (propios + ganancia de terceros)</div>
                    <div style="display:flex; justify-content:space-between;"><span>Efectivo</span><span class="font-semibold">${{ number_format($resumenCajaMec['servicios_efectivo'], 0, ',', '.') }}</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>Transferencia</span><span class="font-semibold">${{ number_format($resumenCajaMec['servicios_transferencia'], 0, ',', '.') }}</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>Crédito</span><span class="font-semibold">${{ number_format($resumenCajaMec['servicios_credito'], 0, ',', '.') }}</span></div>
                    <div style="font-size:9px; color:#9ca3af; margin-top:2px;">En servicios a terceros solo cuenta la ganancia de la empresa (venta − costo); lo que recibe el tercero no pasa por esta caja.</div>

                    <div style="font-size:10px; color:#7c3aed; text-transform:uppercase; font-weight:700; margin:10px 0 4px;">Pagado a mecánicos (liquidaciones + préstamos)</div>
                    <div style="display:flex; justify-content:space-between;"><span>Efectivo</span><span class="font-semibold text-red-700" style="color:#b91c1c;">- ${{ number_format($resumenCajaMec['pagado_efectivo'], 0, ',', '.') }}</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>Transferencia</span><span class="font-semibold" style="color:#b91c1c;">- ${{ number_format($resumenCajaMec['pagado_transferencia'], 0, ',', '.') }}</span></div>

                    <div style="border-top:1px solid #e9d5ff; margin-top:8px; padding-top:8px; display:flex; justify-content:space-between;">
                        <span style="font-weight:700;">Efectivo esperado</span>
                        <span style="font-weight:900; color:{{ $resumenCajaMec['efectivo_esperado'] < 0 ? '#dc2626' : '#16a34a' }};">${{ number_format($resumenCajaMec['efectivo_esperado'], 0, ',', '.') }}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Transferencia neta</span>
                        <span class="font-semibold">${{ number_format($resumenCajaMec['transferencia'], 0, ',', '.') }}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Queda para la empresa</span>
                        <span class="font-semibold" style="color:{{ $resumenCajaMec['queda_empresa'] >= 0 ? '#16a34a' : '#dc2626' }};">${{ number_format($resumenCajaMec['queda_empresa'], 0, ',', '.') }}</span>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563; display:block; margin-bottom:4px;">Efectivo contado en el cajón</label>
                    <input wire:model.live="cajaMecMontoCierre" type="number" min="0" placeholder="0"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; box-sizing:border-box;">
                </div>

                @if($cajaMecMontoCierre !== '')
                    @php $difCajaMec = (float) $cajaMecMontoCierre - (float) $resumenCajaMec['efectivo_esperado']; @endphp
                    <div style="margin-top:10px; text-align:center; font-weight:900; font-size:15px; border-radius:8px; padding:8px;
                        background:{{ $difCajaMec == 0 ? '#dcfce7' : '#fee2e2' }}; color:{{ $difCajaMec == 0 ? '#16a34a' : '#dc2626' }};">
                        @if($difCajaMec == 0)
                            ✅ Cuadra
                        @elseif($difCajaMec > 0)
                            Sobra ${{ number_format($difCajaMec, 0, ',', '.') }}
                        @else
                            Faltó ${{ number_format(abs($difCajaMec), 0, ',', '.') }}
                        @endif
                    </div>
                @endif

                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
                    <button wire:click="$set('modalCajaMecanicos',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Préstamo a mecánico --}}
    @if($modalPrestamo)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1100; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:420px;">
            <div style="background:#d97706; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:15px; font-weight:700;">💸 Registrar préstamo</span>
                <button wire:click="$set('modalPrestamo',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>
            <div style="padding:18px;">
                <div style="margin-bottom:14px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563;">Monto prestado *</label>
                    <input wire:model="prestamoMonto" type="number" step="0.01" min="0" placeholder="0"
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:14px; margin-top:3px;">
                    @error('prestamoMonto') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:11px; font-weight:700; color:#4b5563;">Nota (opcional)</label>
                    <input wire:model="prestamoNota" type="text" placeholder="Motivo del préstamo..."
                        style="width:100%; height:36px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                </div>
                <div style="font-size:11px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 10px; margin-bottom:16px;">
                    Este préstamo se descontará automáticamente del monto que le corresponde al mecánico cuando se liquide.
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button wire:click="$set('modalPrestamo',false)"
                        style="border:1px solid #d1d5db; background:white; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; color:#6b7280;">
                        Cancelar
                    </button>
                    <button wire:click="guardarPrestamo" wire:loading.attr="disabled"
                        style="border:none; background:#d97706; color:white; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                        💸 Registrar préstamo
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
        Livewire.on('notify', (data) => {
            const d = Array.isArray(data) ? data[0] : data;
            if (d.type === 'success') Swal.fire({ icon: 'success', title: d.message, timer: 2000, showConfirmButton: false });
            else Swal.fire({ icon: d.type || 'info', title: d.message, confirmButtonColor: '#0f766e' });
        });
    });
</script>
