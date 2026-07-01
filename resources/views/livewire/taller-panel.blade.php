<div style="height:100%; display:flex; flex-direction:column; background:#f8fafc;">

    {{-- Header --}}
    <div style="padding:10px 16px; background:#0f766e; color:white; flex-shrink:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:16px; font-weight:700;">🔧 Taller</span>
                <input wire:model.live.debounce.300ms="busqueda" type="text" placeholder="Buscar placa, cliente..."
                    style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; width:200px; outline:none; color:#1f2937;">
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                {{-- Filtros estado --}}
                @foreach([''=>'Todas','pendiente'=>'⏳ Pendiente','en_proceso'=>'🔧 En proceso','listo'=>'✅ Listo','entregado'=>'📦 Entregado'] as $val => $lbl)
                <button wire:click="$set('filtroEstado','{{ $val }}')"
                    style="border:none; border-radius:20px; padding:4px 12px; font-size:11px; font-weight:700; cursor:pointer;
                        background:{{ $filtroEstado === $val ? 'white' : 'rgba(255,255,255,.2)' }};
                        color:{{ $filtroEstado === $val ? '#0f766e' : 'white' }};">
                    {{ $lbl }}
                </button>
                @endforeach
                <button wire:click="nuevaOrden"
                    style="border:none; border-radius:20px; padding:5px 16px; font-size:12px; font-weight:700; cursor:pointer; background:#16a34a; color:white;">
                    ➕ Nueva orden
                </button>
            </div>
        </div>
    </div>

    {{-- Grid de órdenes --}}
    <div style="flex:1; overflow-y:auto; padding:16px;">
        @if($ordenes->isEmpty())
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:48px;">🔧</div>
                <div style="margin-top:12px; font-size:15px;">No hay órdenes de taller.</div>
                <button wire:click="nuevaOrden" style="margin-top:16px; border:none; border-radius:10px; padding:10px 24px; background:#0f766e; color:white; font-size:14px; font-weight:700; cursor:pointer;">
                    Crear primera orden
                </button>
            </div>
        @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:12px;">
            @foreach($ordenes as $orden)
                @php
                    $colores = [
                        'pendiente'  => ['bg'=>'#fef3c7','border'=>'#fbbf24','badge'=>'#f59e0b','text'=>'#78350f','icon'=>'⏳'],
                        'en_proceso' => ['bg'=>'#eff6ff','border'=>'#93c5fd','badge'=>'#3b82f6','text'=>'#1e40af','icon'=>'🔧'],
                        'listo'      => ['bg'=>'#f0fdf4','border'=>'#86efac','badge'=>'#16a34a','text'=>'#14532d','icon'=>'✅'],
                        'entregado'  => ['bg'=>'#f8fafc','border'=>'#cbd5e1','badge'=>'#64748b','text'=>'#475569','icon'=>'📦'],
                        'cancelado'  => ['bg'=>'#fef2f2','border'=>'#fca5a5','badge'=>'#ef4444','text'=>'#7f1d1d','icon'=>'❌'],
                    ];
                    $c = $colores[$orden->estado] ?? $colores['pendiente'];
                    $totalRep = $orden->repuestos->sum('subtotal');
                @endphp
                <div style="background:{{ $c['bg'] }}; border:2px solid {{ $c['border'] }}; border-radius:14px; padding:14px; position:relative;
                    {{ $orden->estado === 'listo' ? 'box-shadow:0 0 0 3px #16a34a, 0 0 14px #16a34a55;' : '' }}">

                    {{-- Badge estado + número --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:11px; font-weight:700; background:{{ $c['badge'] }}; color:white; border-radius:99px; padding:2px 10px;">
                            {{ $c['icon'] }} {{ ucfirst(str_replace('_',' ',$orden->estado)) }}
                        </span>
                        <span style="font-size:11px; font-weight:700; color:{{ $c['text'] }};">
                            # {{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    {{-- Vehículo --}}
                    <div style="font-size:18px; font-weight:900; color:#0f766e; letter-spacing:.05em;">{{ $orden->placa }}</div>
                    <div style="font-size:12px; color:#374151; font-weight:600; margin-top:2px;">
                        {{ trim($orden->marca . ' ' . $orden->modelo) ?: '—' }}
                        @if($orden->color) · <span style="color:#6b7280;">{{ $orden->color }}</span> @endif
                        @if($orden->km_ingreso) · <span style="color:#6b7280;">{{ number_format($orden->km_ingreso) }} km</span> @endif
                    </div>

                    {{-- Cliente --}}
                    <div style="margin-top:6px; font-size:12px; color:#374151;">
                        👤 {{ $orden->cliente_nombre }}
                        @if($orden->cliente_telefono)
                            · 📞 {{ $orden->cliente_telefono }}
                        @endif
                    </div>

                    {{-- Diagnóstico --}}
                    @if($orden->diagnostico)
                    <div style="margin-top:6px; font-size:11px; color:#6b7280; background:rgba(0,0,0,.04); border-radius:6px; padding:5px 8px; line-height:1.4;">
                        {{ Str::limit($orden->diagnostico, 100) }}
                    </div>
                    @endif

                    {{-- Repuestos y total --}}
                    @if($orden->repuestos->isNotEmpty())
                    <div style="margin-top:8px; font-size:11px; color:#374151;">
                        🔩 {{ $orden->repuestos->count() }} repuesto(s) ·
                        <strong>${{ number_format($totalRep, 0, ',', '.') }}</strong>
                    </div>
                    @endif

                    <div style="font-size:10px; color:#94a3b8; margin-top:4px;">
                        {{ $orden->created_at->format('d/m/Y h:i A') }}
                    </div>

                    {{-- Acciones --}}
                    <div style="display:flex; gap:6px; margin-top:10px; flex-wrap:wrap;">
                        <button wire:click="editarOrden({{ $orden->id }})"
                            style="flex:1; border:none; border-radius:8px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                            ✏️ Editar
                        </button>

                        @if(in_array($orden->estado, ['pendiente','en_proceso','listo']))
                            {{-- Cambio rápido de estado --}}
                            @if($orden->estado === 'pendiente')
                            <button wire:click="cambiarEstado({{ $orden->id }},'en_proceso')"
                                style="flex:1; border:none; border-radius:8px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#3b82f6; color:white;">
                                🔧 Iniciar
                            </button>
                            @elseif($orden->estado === 'en_proceso')
                            <button wire:click="cambiarEstado({{ $orden->id }},'listo')"
                                style="flex:1; border:none; border-radius:8px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#16a34a; color:white;">
                                ✅ Marcar listo
                            </button>
                            @endif

                            <button wire:click="facturarOrden({{ $orden->id }})"
                                style="flex:1; border:none; border-radius:8px; padding:6px; font-size:11px; font-weight:700; cursor:pointer; background:#4f46e5; color:white;">
                                💵 Facturar
                            </button>
                        @endif

                        @if($orden->estado === 'entregado' && $orden->factura_id)
                        <span style="font-size:10px; color:#16a34a; font-weight:700; padding:6px;">✅ Factura #{{ $orden->factura_id }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Modal nueva/editar orden --}}
    @if($modalOrden)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:16px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto;">

            {{-- Header modal --}}
            <div style="background:#0f766e; color:white; padding:14px 18px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:1;">
                <span style="font-size:15px; font-weight:700;">
                    🔧 {{ $ordenId ? 'Editar orden' : 'Nueva orden de taller' }}
                </span>
                <button wire:click="$set('modalOrden',false)" style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:99px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1;">×</button>
            </div>

            <div style="padding:18px;">

                {{-- Datos cliente --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; margin-bottom:8px;">👤 Cliente</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Nombre *</label>
                        <input wire:model="clienteNombre" type="text" placeholder="Nombre del cliente"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                        @error('clienteNombre') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Teléfono</label>
                        <input wire:model="clienteTelefono" type="text" placeholder="3001234567"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>

                {{-- Datos vehículo --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; margin-bottom:8px;">🚗 Vehículo</div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Placa *</label>
                        <input wire:model="placa" type="text" placeholder="ABC-123" style="text-transform:uppercase;"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:14px; font-weight:700; margin-top:3px; text-transform:uppercase;"
                            oninput="this.value=this.value.toUpperCase()">
                        @error('placa') <span style="font-size:10px; color:#ef4444;">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Marca</label>
                        <input wire:model="marca" type="text" placeholder="Toyota, Renault..."
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Modelo</label>
                        <input wire:model="modelo" type="text" placeholder="Corolla, Logan..."
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Color</label>
                        <input wire:model="color" type="text" placeholder="Blanco, Negro..."
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Kilometraje</label>
                        <input wire:model="kmIngreso" type="text" placeholder="45000"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:700; color:#4b5563;">Estado</label>
                        <select wire:model="estado"
                            style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px; margin-top:3px; background:white;">
                            <option value="pendiente">⏳ Pendiente</option>
                            <option value="en_proceso">🔧 En proceso</option>
                            <option value="listo">✅ Listo</option>
                            <option value="entregado">📦 Entregado</option>
                            <option value="cancelado">❌ Cancelado</option>
                        </select>
                    </div>
                </div>

                {{-- Diagnóstico y observaciones --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; margin-bottom:8px;">📋 Diagnóstico</div>
                <textarea wire:model="diagnostico" rows="3" placeholder="Describe el problema o el trabajo a realizar..."
                    style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; resize:none; margin-bottom:10px;"></textarea>
                <textarea wire:model="observaciones" rows="2" placeholder="Observaciones internas (opcional)..."
                    style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; font-size:13px; resize:none; margin-bottom:14px;"></textarea>

                {{-- Repuestos --}}
                <div style="font-size:11px; font-weight:700; color:#0f766e; text-transform:uppercase; margin-bottom:8px;">🔩 Repuestos usados</div>

                {{-- Buscador de productos --}}
                <div style="position:relative; margin-bottom:10px;">
                    <input wire:model.live.debounce.300ms="buscarProducto" type="text"
                        placeholder="Buscar producto del inventario..."
                        style="width:100%; height:34px; border:1px solid #d1d5db; border-radius:8px; padding:4px 10px; font-size:13px;">
                    @if($productosSugeridos->isNotEmpty())
                    <div style="position:absolute; top:38px; left:0; right:0; background:white; border:1px solid #e2e8f0; border-radius:8px; z-index:50; box-shadow:0 4px 12px rgba(0,0,0,.1); max-height:200px; overflow-y:auto;">
                        @foreach($productosSugeridos as $prod)
                        <div wire:click="agregarProducto({{ $prod->id }})"
                            style="padding:8px 12px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9;"
                            onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='white'">
                            <div>
                                <div style="font-size:12px; font-weight:600; color:#111827;">{{ $prod->nombre }}</div>
                                <div style="font-size:10px; color:#9ca3af;">{{ $prod->codigo }}</div>
                            </div>
                            <div style="font-size:12px; font-weight:700; color:#0f766e;">${{ number_format($prod->precio_venta, 0, ',', '.') }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Lista repuestos --}}
                @if(!empty($repuestos))
                <div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:8px;">
                    <div style="display:grid; grid-template-columns:1fr 80px 100px 90px 32px; gap:0; background:#f8fafc; padding:6px 10px; font-size:10px; font-weight:700; color:#6b7280;">
                        <span>DESCRIPCIÓN</span><span style="text-align:center;">CANT.</span><span style="text-align:center;">PRECIO</span><span style="text-align:right;">SUBTOTAL</span><span></span>
                    </div>
                    @foreach($repuestos as $i => $r)
                    <div style="display:grid; grid-template-columns:1fr 80px 100px 90px 32px; gap:4px; padding:6px 10px; border-top:1px solid #f1f5f9; align-items:center;">
                        <input wire:model.lazy="repuestos.{{ $i }}.descripcion" wire:change="actualizarRepuesto({{ $i }})"
                            type="text" style="height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:2px 6px; font-size:12px; width:100%;">
                        <input wire:model.lazy="repuestos.{{ $i }}.cantidad" wire:change="actualizarRepuesto({{ $i }})"
                            type="number" min="0.01" step="0.01"
                            style="height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:2px 6px; font-size:12px; width:100%; text-align:center;">
                        <input wire:model.lazy="repuestos.{{ $i }}.precio_unitario" wire:change="actualizarRepuesto({{ $i }})"
                            type="number" min="0" step="100"
                            style="height:28px; border:1px solid #e2e8f0; border-radius:6px; padding:2px 6px; font-size:12px; width:100%; text-align:center;">
                        <span style="font-size:12px; font-weight:700; color:#0f766e; text-align:right;">${{ number_format($r['subtotal'], 0, ',', '.') }}</span>
                        <button wire:click="quitarRepuesto({{ $i }})"
                            style="background:#fee2e2; border:none; border-radius:6px; color:#ef4444; font-size:14px; cursor:pointer; width:28px; height:28px; padding:0;">×</button>
                    </div>
                    @endforeach
                    <div style="padding:8px 10px; border-top:2px solid #e2e8f0; display:flex; justify-content:flex-end;">
                        <span style="font-size:13px; font-weight:800; color:#0f766e;">
                            Total repuestos: ${{ number_format(collect($repuestos)->sum('subtotal'), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                @endif

                <button wire:click="agregarRepuestoManual"
                    style="border:1px dashed #cbd5e1; border-radius:8px; padding:6px 14px; font-size:12px; color:#64748b; background:transparent; cursor:pointer; margin-bottom:16px;">
                    + Agregar repuesto manual
                </button>

                {{-- Botones --}}
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button wire:click="$set('modalOrden',false)"
                        style="border:1px solid #d1d5db; border-radius:10px; padding:9px 20px; font-size:13px; font-weight:600; cursor:pointer; background:white; color:#374151;">
                        Cancelar
                    </button>
                    <button wire:click="guardarOrden"
                        style="border:none; border-radius:10px; padding:9px 24px; font-size:13px; font-weight:700; cursor:pointer; background:#0f766e; color:white;">
                        💾 Guardar orden
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
