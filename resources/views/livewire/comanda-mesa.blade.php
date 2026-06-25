<div style="display:flex; flex-direction:column; height:100%; max-height:90vh;">

    {{-- Header --}}
    <div style="padding:14px 16px; background:#4338ca; color:white; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <div>
            <div style="font-size:15px; font-weight:700;">🪑 {{ $mesa->nombre }}</div>
            <div style="font-size:11px; opacity:.8;">
                {{ $mesa->zona ?? '' }}
                @if($orden)
                    &nbsp;·&nbsp;Comanda #{{ $orden->id }}
                    &nbsp;·&nbsp;
                    <span style="background:rgba(255,255,255,.2); border-radius:99px; padding:1px 8px; font-size:10px;">
                        {{ $orden->estado }}
                    </span>
                @endif
            </div>
        </div>
        <button wire:click="$dispatch('cerrar-comanda')"
                style="background:rgba(255,255,255,.2); border:none; color:white; border-radius:8px; padding:6px 12px; cursor:pointer; font-size:13px;">
            ✕ Cerrar
        </button>
    </div>

    <div style="display:flex; flex:1; min-height:0; overflow:hidden;">

        {{-- Columna izquierda: buscar y agregar productos --}}
        <div style="width:48%; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; padding:12px;">
            <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:8px;">Agregar productos</div>

            <input wire:model.live.debounce.300ms="busqueda"
                   placeholder="Buscar producto..."
                   style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; margin-bottom:8px; outline:none;"
                   autofocus />

            <div style="flex:1; overflow-y:auto;">
                @forelse($productos as $producto)
                    <div wire:click="agregarProducto({{ $producto->id }})"
                         style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:6px; cursor:pointer; background:white;"
                         onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='white'">
                        <div>
                            <div style="font-size:12px; font-weight:600; color:#1e293b;">{{ $producto->descripcion_larga }}</div>
                            <div style="font-size:10px; color:#64748b;">Cód: {{ $producto->id_producto }}</div>
                        </div>
                        <div style="font-size:12px; font-weight:700; color:#4338ca;">
                            ${{ number_format($producto->precio_venta1, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    @if(strlen($busqueda) >= 2)
                        <div style="text-align:center; color:#94a3b8; font-size:12px; padding:20px;">Sin resultados</div>
                    @else
                        <div style="text-align:center; color:#94a3b8; font-size:12px; padding:20px;">Escriba al menos 2 caracteres</div>
                    @endif
                @endforelse
            </div>
        </div>

        {{-- Columna derecha: items de la comanda --}}
        <div style="flex:1; display:flex; flex-direction:column; padding:12px;">
            <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:8px;">Comanda</div>

            <div style="flex:1; overflow-y:auto;">
                @if(! $orden || $orden->items->isEmpty())
                    <div style="text-align:center; color:#94a3b8; font-size:12px; padding:30px 0;">
                        Sin productos aún.<br>Agregue desde la izquierda.
                    </div>
                @else
                    @foreach($orden->items as $item)
                        @php
                            $estadoColor = match($item->estado_cocina) {
                                'enviado'    => '#3b82f6',
                                'preparando' => '#f59e0b',
                                'listo'      => '#22c55e',
                                'entregado'  => '#94a3b8',
                                default      => '#64748b',
                            };
                            $estadoLabel = match($item->estado_cocina) {
                                'enviado'    => '📤 Enviado',
                                'preparando' => '🍳 Preparando',
                                'listo'      => '✅ Listo',
                                'entregado'  => '🍽️ Entregado',
                                default      => '⏳ Pendiente',
                            };
                        @endphp
                        <div style="display:flex; align-items:center; gap:8px; padding:7px 8px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:6px; background:white;">
                            {{-- Cantidad --}}
                            <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                                @if($item->estado_cocina === 'pendiente')
                                    <button wire:click="cambiarCantidad({{ $item->id }}, -1)"
                                            style="width:22px;height:22px;background:#fee2e2;color:#ef4444;border:none;border-radius:50%;font-size:14px;cursor:pointer;line-height:1;">−</button>
                                @endif
                                <span style="font-size:13px; font-weight:700; min-width:20px; text-align:center;">{{ (int)$item->cantidad }}</span>
                                @if($item->estado_cocina === 'pendiente')
                                    <button wire:click="cambiarCantidad({{ $item->id }}, 1)"
                                            style="width:22px;height:22px;background:#dcfce7;color:#16a34a;border:none;border-radius:50%;font-size:14px;cursor:pointer;line-height:1;">+</button>
                                @endif
                            </div>

                            {{-- Nombre --}}
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:11px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    {{ $item->producto->descripcion_larga ?? '—' }}
                                </div>
                                <div style="font-size:10px; color:#64748b;">
                                    ${{ number_format($item->precio_unitario, 0, ',', '.') }} c/u
                                </div>
                            </div>

                            {{-- Estado --}}
                            <div style="font-size:9px; font-weight:600; color:{{ $estadoColor }}; text-align:right; flex-shrink:0;">
                                {{ $estadoLabel }}
                            </div>

                            {{-- Subtotal --}}
                            <div style="font-size:11px; font-weight:700; color:#4338ca; flex-shrink:0;">
                                ${{ number_format($item->subtotal, 0, ',', '.') }}
                            </div>

                            {{-- Borrar (solo pendiente) --}}
                            @if($item->estado_cocina === 'pendiente')
                                <button wire:click="eliminarItem({{ $item->id }})"
                                        style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;flex-shrink:0;">🗑</button>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Total --}}
            @if($orden)
                <div style="border-top:2px solid #e2e8f0; padding-top:8px; margin-top:8px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:700; color:#1e293b;">
                        <span>TOTAL</span>
                        <span style="color:#4338ca;">${{ number_format($orden->total, 0, ',', '.') }}</span>
                    </div>
                </div>
            @endif

            {{-- Observaciones --}}
            <textarea wire:model="observaciones" placeholder="Observaciones para cocina..."
                      style="width:100%; margin-top:8px; padding:6px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:11px; resize:none; height:48px; outline:none;"></textarea>
        </div>
    </div>

    {{-- Footer acciones --}}
    <div style="padding:10px 16px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap;">

        @php $hayPendientes = $orden && $orden->items->where('estado_cocina','pendiente')->isNotEmpty(); @endphp

        <button wire:click="enviarACocina"
                @if(! $hayPendientes) disabled @endif
                style="flex:1; padding:9px; background:{{ $hayPendientes ? '#2563eb' : '#94a3b8' }}; color:white; border:none; border-radius:9999px; font-size:12px; font-weight:600; cursor:{{ $hayPendientes ? 'pointer' : 'not-allowed' }};">
            📤 Enviar a cocina
        </button>

        <button wire:click="facturar"
                @if(! $orden) disabled @endif
                style="flex:1; padding:9px; background:{{ $orden ? '#16a34a' : '#94a3b8' }}; color:white; border:none; border-radius:9999px; font-size:12px; font-weight:600; cursor:{{ $orden ? 'pointer' : 'not-allowed' }};">
            💳 Facturar mesa
        </button>

        <button wire:click="liberarMesa"
                onclick="return confirm('¿Liberar esta mesa? Se cancelará la comanda activa.')"
                style="padding:9px 14px; background:#fee2e2; color:#ef4444; border:1px solid #fca5a5; border-radius:9999px; font-size:12px; font-weight:600; cursor:pointer;">
            🔓 Liberar
        </button>
    </div>
</div>
