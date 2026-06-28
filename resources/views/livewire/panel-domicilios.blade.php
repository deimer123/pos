<div x-data="{}" style="font-family:inherit;">

    {{-- Notificaciones --}}
    <div x-data="{ msg: null, type: 'success' }"
        @notify.window="msg = $event.detail.message; type = $event.detail.type; setTimeout(()=>msg=null,3500)"
        x-show="msg" x-cloak x-transition
        style="position:fixed; top:16px; right:16px; z-index:99999; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:700; color:white; box-shadow:0 4px 16px rgba(0,0,0,.2);"
        :style="{ background: type==='success' ? '#16a34a' : '#dc2626' }"
        x-text="msg">
    </div>

    {{-- HEADER --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div>
            <h2 style="font-size:20px; font-weight:800; color:#1e293b; margin:0;">🛵 Panel de Domicilios</h2>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0;">Gestión de pedidos a domicilio</p>
        </div>
        <button wire:click="abrirNuevo"
            style="background:#2563eb; color:white; border:none; border-radius:10px; padding:10px 20px; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;">
            ➕ Nuevo Domicilio
        </button>
    </div>

    {{-- FILTROS --}}
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; align-items:center;">
        <input type="text" wire:model.live.debounce.400ms="filtroBuscar"
            placeholder="Buscar por nombre, teléfono, dirección..."
            style="flex:1; min-width:200px; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 12px; font-size:12px; outline:none;">

        @php
            $estadosFiltro = [
                'todos'     => ['label'=>'Todos',      'color'=>'#64748b'],
                'pendiente' => ['label'=>'Pendientes', 'color'=>'#f59e0b'],
                'asignado'  => ['label'=>'Asignados',  'color'=>'#3b82f6'],
                'en_camino' => ['label'=>'En camino',  'color'=>'#8b5cf6'],
                'entregado' => ['label'=>'Entregados', 'color'=>'#22c55e'],
                'cancelado' => ['label'=>'Cancelados', 'color'=>'#ef4444'],
            ];
        @endphp
        @foreach($estadosFiltro as $key => $ef)
            <button wire:click="$set('filtroEstado','{{ $key }}')"
                style="height:36px; padding:0 14px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; border:2px solid {{ $ef['color'] }}; background:{{ $filtroEstado===$key ? $ef['color'] : 'white' }}; color:{{ $filtroEstado===$key ? 'white' : $ef['color'] }}; white-space:nowrap; transition:all .15s;">
                {{ $ef['label'] }}
                @if($key !== 'todos' && isset($contadores[$key]))
                    <span style="background:{{ $filtroEstado===$key ? 'rgba(255,255,255,.3)' : $ef['color'] }}; color:white; border-radius:20px; padding:1px 7px; margin-left:4px; font-size:10px;">{{ $contadores[$key] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- LISTA DOMICILIOS --}}
    <div style="display:flex; flex-direction:column; gap:10px;">
        @forelse($domicilios as $d)
            @php $ei = \App\Models\Domicilio::$estados[$d->estado] ?? ['label'=>$d->estado,'color'=>'#6b7280','icon'=>'?']; @endphp
            <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 4px rgba(0,0,0,.05); overflow:hidden;">

                {{-- Franja de estado --}}
                <div style="height:4px; background:{{ $ei['color'] }};"></div>

                <div style="padding:12px 14px; display:flex; align-items:flex-start; gap:12px; flex-wrap:wrap;">

                    {{-- ID + Estado --}}
                    <div style="display:flex; flex-direction:column; align-items:center; gap:4px; min-width:56px;">
                        <div style="font-size:11px; font-weight:800; color:#94a3b8;">#{{ $d->id }}</div>
                        <div style="background:{{ $ei['color'] }}22; color:{{ $ei['color'] }}; border-radius:20px; padding:3px 8px; font-size:10px; font-weight:700; white-space:nowrap;">
                            {{ $ei['icon'] }} {{ $ei['label'] }}
                        </div>
                        <div style="font-size:9px; color:#94a3b8;">{{ $d->created_at->format('d/m H:i') }}</div>
                    </div>

                    {{-- Cliente + Dirección --}}
                    <div style="flex:1; min-width:180px;">
                        <div style="font-size:14px; font-weight:800; color:#1e293b;">{{ $d->cliente_nombre }}</div>
                        @if($d->cliente_telefono)
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">📞 {{ $d->cliente_telefono }}</div>
                        @endif
                        <div style="font-size:12px; color:#374151; margin-top:4px; font-weight:600;">📍 {{ $d->direccion }}</div>
                        @if($d->barrio)
                            <div style="font-size:11px; color:#64748b;">{{ $d->barrio }}{{ $d->ciudad ? ', '.$d->ciudad : '' }}</div>
                        @endif
                        @if($d->referencia)
                            <div style="font-size:10px; color:#94a3b8; margin-top:2px; font-style:italic;">{{ $d->referencia }}</div>
                        @endif
                    </div>

                    {{-- Items resumen --}}
                    @if($d->items->count())
                    <div style="min-width:140px; background:#f8fafc; border-radius:8px; padding:6px 10px;">
                        <div style="font-size:10px; font-weight:700; color:#64748b; margin-bottom:4px; text-transform:uppercase;">Pedido</div>
                        @foreach($d->items->take(3) as $item)
                            <div style="font-size:11px; color:#374151;">{{ $item->cantidad }}x {{ Str::limit($item->nombre_producto,22) }}</div>
                        @endforeach
                        @if($d->items->count() > 3)
                            <div style="font-size:10px; color:#94a3b8;">+{{ $d->items->count()-3 }} más...</div>
                        @endif
                    </div>
                    @endif

                    {{-- Totales --}}
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:2px; min-width:100px;">
                        @if($d->total_pedido > 0)
                            <div style="font-size:11px; color:#64748b;">Pedido: <b>${{ number_format($d->total_pedido,0,',','.') }}</b></div>
                        @endif
                        @if($d->valor_domicilio > 0)
                            <div style="font-size:11px; color:#64748b;">Domicilio: <b>${{ number_format($d->valor_domicilio,0,',','.') }}</b></div>
                        @endif
                        <div style="font-size:15px; font-weight:900; color:#0f766e;">${{ number_format($d->total_pedido + $d->valor_domicilio,0,',','.') }}</div>
                        @if($d->repartidor)
                            <div style="font-size:10px; color:#3b82f6; font-weight:700; margin-top:4px;">🛵 {{ $d->repartidor->name }}</div>
                        @endif
                        @if($d->venta_id)
                            <div style="font-size:10px; color:#16a34a; font-weight:600;">🧾 Venta #{{ $d->venta_id }}</div>
                        @endif
                    </div>

                    {{-- Acciones --}}
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:stretch; min-width:110px;">

                        <button wire:click="verDetalle({{ $d->id }})"
                            style="height:30px; background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                            👁 Ver detalle
                        </button>

                        @if(in_array($d->estado, ['pendiente','asignado']))
                            <button wire:click="abrirAsignar({{ $d->id }})"
                                style="height:30px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                                🛵 Asignar
                            </button>
                        @endif

                        @if($d->estado === 'asignado')
                            <button wire:click="cambiarEstado({{ $d->id }},'en_camino')"
                                style="height:30px; background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                                🚀 En camino
                            </button>
                        @endif

                        @if($d->estado === 'en_camino')
                            <button wire:click="cambiarEstado({{ $d->id }},'entregado')"
                                style="height:30px; background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                                ✅ Entregado
                            </button>
                        @endif

                        @if(!in_array($d->estado, ['entregado','cancelado']))
                            <button wire:click="abrirEditar({{ $d->id }})"
                                style="height:30px; background:#fefce8; color:#a16207; border:1px solid #fde68a; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                                ✏️ Editar
                            </button>
                            <button
                                x-on:click="Swal.fire({title:'¿Cancelar domicilio?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Sí, cancelar',cancelButtonText:'No'}).then(r=>{ if(r.isConfirmed) $wire.cancelar({{ $d->id }}) })"
                                style="height:30px; background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:7px; font-size:11px; font-weight:700; cursor:pointer;">
                                ❌ Cancelar
                            </button>
                        @endif
                    </div>

                </div>
            </div>
        @empty
            <div style="text-align:center; padding:60px 16px; color:#94a3b8;">
                <div style="font-size:48px; margin-bottom:12px;">🛵</div>
                <div style="font-size:15px; font-weight:700;">No hay domicilios</div>
                <div style="font-size:12px; margin-top:4px;">Crea el primer pedido con el botón de arriba</div>
            </div>
        @endforelse
    </div>

    {{-- ===================== MODAL NUEVO / EDITAR ===================== --}}
    @if($mostrarModal)
    <div style="position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.6); padding:16px;">
        <div style="background:white; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.3); width:min(720px,100%); max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">

            {{-- Header modal --}}
            <div style="background:linear-gradient(135deg,#1e40af,#2563eb); color:white; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
                <h3 style="margin:0; font-size:16px; font-weight:800;">{{ $domicilioId ? '✏️ Editar Domicilio #'.$domicilioId : '➕ Nuevo Domicilio' }}</h3>
                <button wire:click="$set('mostrarModal',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:8px; width:30px; height:30px; cursor:pointer; font-size:16px;">✕</button>
            </div>

            <div style="overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:16px;">

                {{-- DATOS CLIENTE --}}
                <div style="background:#f8fafc; border-radius:10px; padding:14px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">👤 Datos del Cliente</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11px; font-weight:700; color:#374151;">Nombre *</label>
                            <input wire:model="clienteNombre" type="text" placeholder="Nombre completo"
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                            @error('clienteNombre') <span style="font-size:10px; color:#dc2626;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151;">Teléfono</label>
                            <input wire:model="clienteTelefono" type="text" placeholder="Ej: 3001234567"
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151;">Ciudad</label>
                            <input wire:model="ciudad" type="text" placeholder="Ciudad"
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                        </div>
                    </div>
                </div>

                {{-- DIRECCIÓN --}}
                <div style="background:#f8fafc; border-radius:10px; padding:14px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">📍 Dirección de Entrega</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11px; font-weight:700; color:#374151;">Dirección *</label>
                            <input wire:model="direccion" type="text" placeholder="Calle, Carrera, Avenida..."
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                            @error('direccion') <span style="font-size:10px; color:#dc2626;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151;">Barrio</label>
                            <input wire:model="barrio" type="text" placeholder="Barrio"
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151;">Valor domicilio</label>
                            <input wire:model="valorDomicilio" type="number" min="0" placeholder="0"
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11px; font-weight:700; color:#374151;">Referencia / Punto de referencia</label>
                            <input wire:model="referencia" type="text" placeholder="Ej: Casa azul, al lado del parque..."
                                style="width:100%; height:36px; border:1px solid #e2e8f0; border-radius:8px; padding:0 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11px; font-weight:700; color:#374151;">Observaciones</label>
                            <textarea wire:model="observaciones" rows="2" placeholder="Notas adicionales del pedido..."
                                style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; font-size:13px; margin-top:3px; box-sizing:border-box; outline:none; resize:vertical;"></textarea>
                        </div>
                    </div>
                </div>

                {{-- ITEMS DEL PEDIDO --}}
                <div style="background:#f8fafc; border-radius:10px; padding:14px; border:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                        <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em;">🛍 Productos del Pedido</div>
                        <button wire:click="agregarItemManual" type="button"
                            style="background:#2563eb; color:white; border:none; border-radius:7px; padding:5px 12px; font-size:11px; font-weight:700; cursor:pointer;">
                            + Agregar producto
                        </button>
                    </div>

                    @foreach($items as $idx => $item)
                    <div style="display:grid; grid-template-columns:1fr 70px 90px 36px; gap:6px; margin-bottom:6px; align-items:center;">
                        <input wire:model.lazy="items.{{ $idx }}.nombre_producto" type="text" placeholder="Nombre del producto"
                            style="height:32px; border:1px solid #e2e8f0; border-radius:7px; padding:0 8px; font-size:12px; outline:none;">
                        <input wire:model.lazy="items.{{ $idx }}.cantidad" type="number" min="1" step="0.01" placeholder="Cant."
                            wire:change="recalcularItems"
                            style="height:32px; border:1px solid #e2e8f0; border-radius:7px; padding:0 6px; font-size:12px; text-align:center; outline:none;">
                        <input wire:model.lazy="items.{{ $idx }}.precio" type="number" min="0" placeholder="Precio"
                            wire:change="recalcularItems"
                            style="height:32px; border:1px solid #e2e8f0; border-radius:7px; padding:0 6px; font-size:12px; text-align:right; outline:none;">
                        <button wire:click="quitarItem({{ $idx }})" type="button"
                            style="width:32px; height:32px; background:#fee2e2; color:#dc2626; border:none; border-radius:7px; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center;">✕</button>
                    </div>
                    @endforeach

                    @if(count($items))
                    <div style="text-align:right; font-size:13px; font-weight:800; color:#0f766e; margin-top:8px; padding-top:8px; border-top:1px solid #e2e8f0;">
                        Total pedido: ${{ number_format(collect($items)->sum('total'),0,',','.') }}
                    </div>
                    @endif
                </div>

            </div>

            {{-- Footer modal --}}
            <div style="padding:14px 20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; flex-shrink:0; background:white;">
                <button wire:click="$set('mostrarModal',false)"
                    style="height:38px; padding:0 20px; background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer;">
                    Cancelar
                </button>
                <button wire:click="guardar" wire:loading.attr="disabled"
                    style="height:38px; padding:0 24px; background:#2563eb; color:white; border:none; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer;">
                    <span wire:loading.remove wire:target="guardar">💾 Guardar</span>
                    <span wire:loading wire:target="guardar">Guardando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ===================== MODAL ASIGNAR REPARTIDOR ===================== --}}
    @if($mostrarModalAsignar)
    <div style="position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.6); padding:16px;">
        <div style="background:white; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.3); width:min(400px,100%); overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e40af,#3b82f6); color:white; padding:14px 18px; display:flex; align-items:center; justify-content:space-between;">
                <h3 style="margin:0; font-size:15px; font-weight:800;">🛵 Asignar Repartidor</h3>
                <button wire:click="$set('mostrarModalAsignar',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:7px; width:28px; height:28px; cursor:pointer;">✕</button>
            </div>
            <div style="padding:20px;">
                <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:8px;">Seleccionar repartidor:</label>
                @forelse($repartidores as $rep)
                    <div wire:click="$set('repartidorSeleccionado',{{ $rep->id }})"
                        style="padding:10px 14px; border-radius:9px; border:2px solid {{ $repartidorSeleccionado==$rep->id ? '#2563eb' : '#e2e8f0' }}; background:{{ $repartidorSeleccionado==$rep->id ? '#eff6ff' : 'white' }}; margin-bottom:6px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all .15s;">
                        <div style="width:36px; height:36px; border-radius:50%; background:#dbeafe; display:flex; align-items:center; justify-content:center; font-size:16px;">🛵</div>
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#1e293b;">{{ $rep->name }}</div>
                            <div style="font-size:11px; color:#64748b;">{{ $rep->email }}</div>
                        </div>
                        @if($repartidorSeleccionado==$rep->id)
                            <div style="margin-left:auto; color:#2563eb; font-size:18px; font-weight:900;">✓</div>
                        @endif
                    </div>
                @empty
                    <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">
                        No hay repartidores registrados.<br>
                        <small>Crea un empleado con rol <b>Repartidor</b>.</small>
                    </div>
                @endforelse
            </div>
            <div style="padding:12px 18px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:8px; background:white;">
                <button wire:click="$set('mostrarModalAsignar',false)"
                    style="height:36px; padding:0 16px; background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
                    Cancelar
                </button>
                <button wire:click="confirmarAsignar" wire:loading.attr="disabled"
                    style="height:36px; padding:0 20px; background:#2563eb; color:white; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
                    ✅ Confirmar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ===================== MODAL DETALLE ===================== --}}
    @if($mostrarDetalle && $domicilioDetalle)
    @php $ei2 = \App\Models\Domicilio::$estados[$domicilioDetalle->estado] ?? ['label'=>$domicilioDetalle->estado,'color'=>'#6b7280','icon'=>'?']; @endphp
    <div style="position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.6); padding:16px;">
        <div style="background:white; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.3); width:min(520px,100%); max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e293b,#334155); color:white; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
                <h3 style="margin:0; font-size:15px; font-weight:800;">📋 Domicilio #{{ $domicilioDetalle->id }}</h3>
                <button wire:click="$set('mostrarDetalle',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:7px; width:28px; height:28px; cursor:pointer;">✕</button>
            </div>
            <div style="overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:12px;">

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <span style="background:{{ $ei2['color'] }}22; color:{{ $ei2['color'] }}; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:800;">{{ $ei2['icon'] }} {{ $ei2['label'] }}</span>
                    <span style="background:#f1f5f9; color:#64748b; border-radius:20px; padding:4px 12px; font-size:11px; font-weight:600;">{{ $domicilioDetalle->created_at->format('d/m/Y H:i') }}</span>
                </div>

                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid #e2e8f0;">
                    <div style="font-size:10px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px;">👤 Cliente</div>
                    <div style="font-size:15px; font-weight:800; color:#1e293b;">{{ $domicilioDetalle->cliente_nombre }}</div>
                    @if($domicilioDetalle->cliente_telefono)
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">📞 {{ $domicilioDetalle->cliente_telefono }}</div>
                    @endif
                </div>

                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid #e2e8f0;">
                    <div style="font-size:10px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px;">📍 Dirección</div>
                    <div style="font-size:13px; font-weight:700; color:#1e293b;">{{ $domicilioDetalle->direccion }}</div>
                    @if($domicilioDetalle->barrio) <div style="font-size:12px; color:#64748b;">{{ $domicilioDetalle->barrio }}{{ $domicilioDetalle->ciudad ? ', '.$domicilioDetalle->ciudad : '' }}</div> @endif
                    @if($domicilioDetalle->referencia) <div style="font-size:11px; color:#94a3b8; margin-top:4px; font-style:italic;">{{ $domicilioDetalle->referencia }}</div> @endif
                </div>

                @if($domicilioDetalle->items->count())
                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid #e2e8f0;">
                    <div style="font-size:10px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px;">🛍 Productos</div>
                    @foreach($domicilioDetalle->items as $item)
                    <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #f1f5f9; font-size:12px;">
                        <span style="color:#374151; font-weight:600;">{{ $item->cantidad }}x {{ $item->nombre_producto }}</span>
                        <span style="color:#0f766e; font-weight:800;">${{ number_format($item->total,0,',','.') }}</span>
                    </div>
                    @endforeach
                    <div style="display:flex; justify-content:space-between; padding-top:8px; font-size:13px; font-weight:800;">
                        <span style="color:#374151;">Subtotal pedido</span>
                        <span style="color:#0f766e;">${{ number_format($domicilioDetalle->total_pedido,0,',','.') }}</span>
                    </div>
                    @if($domicilioDetalle->valor_domicilio > 0)
                    <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
                        <span>Valor domicilio</span>
                        <span>${{ number_format($domicilioDetalle->valor_domicilio,0,',','.') }}</span>
                    </div>
                    @endif
                    <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:1px solid #e2e8f0; margin-top:6px; font-size:15px; font-weight:900; color:#0f766e;">
                        <span>TOTAL</span>
                        <span>${{ number_format($domicilioDetalle->total_pedido + $domicilioDetalle->valor_domicilio,0,',','.') }}</span>
                    </div>
                </div>
                @endif

                @if($domicilioDetalle->observaciones)
                <div style="background:#fffbeb; border-radius:10px; padding:10px 12px; border:1px solid #fde68a; font-size:12px; color:#92400e;">
                    📝 {{ $domicilioDetalle->observaciones }}
                </div>
                @endif

                @if($domicilioDetalle->repartidor)
                <div style="background:#eff6ff; border-radius:10px; padding:10px 12px; border:1px solid #bfdbfe; font-size:12px; color:#1d4ed8; font-weight:700;">
                    🛵 Repartidor: {{ $domicilioDetalle->repartidor->name }}
                </div>
                @endif

                @if($domicilioDetalle->venta_id || $domicilioDetalle->factura_id)
                <div style="background:#f0fdf4; border-radius:10px; padding:10px 12px; border:1px solid #bbf7d0; font-size:12px; color:#15803d; font-weight:700;">
                    🧾 @if($domicilioDetalle->venta_id) Venta #{{ $domicilioDetalle->venta_id }} @endif
                       @if($domicilioDetalle->factura_id) | Factura #{{ $domicilioDetalle->factura_id }} @endif
                </div>
                @endif

            </div>
            <div style="padding:12px 18px; border-top:1px solid #e2e8f0; background:white; flex-shrink:0;">
                <button wire:click="$set('mostrarDetalle',false)"
                    style="width:100%; height:38px; background:#1e293b; color:white; border:none; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer;">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
