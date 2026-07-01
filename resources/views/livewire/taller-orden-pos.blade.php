<div style="height:100%; display:flex; flex-direction:column; background:#f0fdf4;">

    {{-- ═══════ MODAL FACTURAR ═══════ --}}
    @if($modalFacturar)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;">
        <div style="background:white;border-radius:16px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="background:#0f766e;color:white;padding:16px 20px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:16px;font-weight:700;">💵 Confirmar factura</div>
                <button wire:click="$set('modalFacturar',false)" style="background:rgba(255,255,255,.2);border:none;color:white;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:16px;">✕</button>
            </div>
            <div style="padding:20px;">
                <div style="background:#f0fdf4;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
                    <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Total a cobrar</div>
                    <div style="font-size:28px;font-weight:900;color:#0f766e;">${{ number_format($total, 0, ',', '.') }}</div>
                    <div style="font-size:11px;color:#6b7280;margin-top:4px;">Orden #{{ str_pad($orden->numero_orden,4,'0',STR_PAD_LEFT) }} · {{ $orden->placa }} · {{ $orden->cliente_nombre }}</div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                    <div>
                        <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:4px;">Tipo de pago</label>
                        <select wire:model="tipoPago" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:4px;">Medio de pago</label>
                        <select wire:model="medioPago" style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="nequi">Nequi / Daviplata</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:4px;">Observaciones (opcional)</label>
                    <input wire:model="obsFactura" type="text" placeholder="Ej: Cambio de aceite + frenos..."
                        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                </div>

                <div style="display:flex;gap:10px;">
                    <button wire:click="facturar" wire:loading.attr="disabled"
                        style="flex:1;background:#0f766e;color:white;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">
                        <span wire:loading.remove wire:target="facturar">✅ Facturar</span>
                        <span wire:loading wire:target="facturar">Procesando...</span>
                    </button>
                    <button wire:click="$set('modalFacturar',false)"
                        style="flex:1;background:#e5e7eb;color:#374151;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════ HEADER INFO ORDEN ═══════ --}}
    <div style="background:#0f766e;color:white;padding:10px 16px;flex-shrink:0;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            {{-- Placa destacada --}}
            <div style="background:white;color:#0f766e;border-radius:8px;padding:4px 14px;font-size:20px;font-weight:900;letter-spacing:2px;">
                {{ $orden->placa }}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:700;">
                    {{ trim($orden->marca . ' ' . $orden->modelo) ?: 'Vehículo' }}
                    @if($orden->color) · {{ $orden->color }}@endif
                    @if($orden->km_ingreso) · <span style="font-size:11px;opacity:.8;">{{ number_format($orden->km_ingreso) }} km</span>@endif
                </div>
                <div style="font-size:12px;opacity:.85;margin-top:2px;">
                    👤 {{ $orden->cliente_nombre }}
                    @if($orden->cliente_telefono) · 📞 {{ $orden->cliente_telefono }}@endif
                </div>
                @if($orden->diagnostico)
                <div style="font-size:11px;opacity:.75;margin-top:2px;background:rgba(255,255,255,.1);border-radius:5px;padding:2px 8px;display:inline-block;">
                    {{ Str::limit($orden->diagnostico, 100) }}
                </div>
                @endif
            </div>
            {{-- Estado + acciones rápidas --}}
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                @php
                    $estadosTaller = [
                        'pendiente'  => ['label'=>'Pendiente',   'bg'=>'#fbbf24','icon'=>'⏳'],
                        'en_proceso' => ['label'=>'En proceso',  'bg'=>'#3b82f6','icon'=>'🔧'],
                        'listo'      => ['label'=>'Listo',       'bg'=>'#16a34a','icon'=>'✅'],
                        'entregado'  => ['label'=>'Entregado',   'bg'=>'#64748b','icon'=>'📦'],
                        'cancelado'  => ['label'=>'Cancelado',   'bg'=>'#ef4444','icon'=>'❌'],
                    ];
                    $est = $estadosTaller[$orden->estado] ?? $estadosTaller['pendiente'];
                @endphp
                <span style="background:{{ $est['bg'] }};color:white;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;">
                    {{ $est['icon'] }} {{ $est['label'] }}
                </span>
                @if($orden->estado === 'pendiente')
                    <button wire:click="cambiarEstado('en_proceso')" style="background:rgba(255,255,255,.2);border:none;color:white;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">🔧 Iniciar</button>
                @elseif($orden->estado === 'en_proceso')
                    <button wire:click="cambiarEstado('listo')" style="background:rgba(255,255,255,.2);border:none;color:white;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">✅ Marcar listo</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════ BODY: izquierda búsqueda / derecha orden ═══════ --}}
    <div style="flex:1;overflow:hidden;display:flex;">

        {{-- ─ Izquierda: búsqueda de productos ─ --}}
        <div style="width:55%;border-right:2px solid #d1fae5;display:flex;flex-direction:column;background:white;">

            {{-- Buscador --}}
            <div style="padding:12px 14px;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
                <input wire:model.live.debounce.300ms="buscarProducto"
                    type="text" placeholder="🔍 Buscar repuesto o producto..."
                    style="width:100%;border:2px solid #d1fae5;border-radius:10px;padding:10px 14px;font-size:14px;box-sizing:border-box;outline:none;"
                    onfocus="this.style.borderColor='#0f766e'" onblur="this.style.borderColor='#d1fae5'">
            </div>

            {{-- Sugerencias --}}
            <div style="flex:1;overflow-y:auto;padding:10px 14px;">
                @if(strlen($buscarProducto) >= 2)
                    @if($productosSugeridos->isEmpty())
                        <div style="text-align:center;padding:30px;color:#94a3b8;font-size:13px;">No se encontraron productos.</div>
                    @else
                        @foreach($productosSugeridos as $p)
                        <div onclick="@this.call('agregarProducto', {{ $p->id }})"
                             style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;margin-bottom:6px;cursor:pointer;background:#f9fafb;"
                             onmouseover="this.style.background='#f0fdf4';this.style.borderColor='#86efac'" onmouseout="this.style.background='#f9fafb';this.style.borderColor='#e5e7eb'">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:#111827;">{{ $p->nombre }}</div>
                                <div style="font-size:11px;color:#9ca3af;">Cód: {{ $p->codigo }}</div>
                            </div>
                            <div style="font-size:14px;font-weight:700;color:#0f766e;">${{ number_format($p->precio_venta, 0, ',', '.') }}</div>
                        </div>
                        @endforeach
                    @endif
                @else
                    {{-- Botones para agregar ítems manuales --}}
                    <div style="padding:20px 0;">
                        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Agregar manualmente</div>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <button wire:click="agregarManual('repuesto')"
                                style="text-align:left;background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#166534;">
                                🔩 Repuesto / Pieza manual
                                <span style="font-size:11px;font-weight:400;color:#6b7280;display:block;">Descuenta del inventario y afecta ventas del día</span>
                            </button>
                            <button wire:click="agregarManual('mano_obra')"
                                style="text-align:left;background:#eff6ff;border:2px solid #93c5fd;border-radius:10px;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#1e40af;">
                                🔧 Mano de obra / Servicio propio
                                <span style="font-size:11px;font-weight:400;color:#6b7280;display:block;">Afecta ventas del día con ganancia configurada</span>
                            </button>
                            <button wire:click="agregarManual('tercero')"
                                style="text-align:left;background:#fdf4ff;border:2px solid #e879f9;border-radius:10px;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#7e22ce;">
                                🏢 Servicio de tercero
                                <span style="font-size:11px;font-weight:400;color:#6b7280;display:block;">Solo control de inventario, NO afecta ventas del día</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ─ Fotos / evidencia ─ --}}
            <div style="border-top:2px solid #d1fae5;padding:12px 14px;flex-shrink:0;background:#f8fafc;max-height:200px;overflow-y:auto;">
                <div style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">📸 Evidencia fotográfica</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
                    @forelse($orden->fotos ?? [] as $i => $foto)
                    <div style="position:relative;">
                        <img src="{{ Storage::url($foto) }}" alt="Foto"
                            style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:2px solid #d1fae5;cursor:pointer;"
                            onclick="window.open('{{ Storage::url($foto) }}','_blank')">
                        <button wire:click="eliminarFoto({{ $i }})"
                            style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:white;border:none;border-radius:50%;width:18px;height:18px;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">✕</button>
                    </div>
                    @empty
                    @endforelse
                    {{-- Upload --}}
                    <label style="width:70px;height:70px;background:#e0fce7;border:2px dashed #86efac;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:2px;">
                        <span style="font-size:20px;">📷</span>
                        <span style="font-size:9px;color:#16a34a;font-weight:600;">Agregar</span>
                        <input type="file" wire:model="fotoTemp" accept="image/*" style="display:none;" onchange="@this.call('subirFoto')">
                    </label>
                </div>
            </div>
        </div>

        {{-- ─ Derecha: ítems de la orden ─ --}}
        <div style="flex:1;display:flex;flex-direction:column;background:#f8fafc;">

            {{-- Lista de ítems --}}
            <div style="flex:1;overflow-y:auto;padding:12px 14px;">
                @forelse($orden->repuestos as $r)
                @php
                    $tipoBg = match($r->tipo ?? 'repuesto') {
                        'mano_obra' => '#eff6ff',
                        'tercero'   => '#fdf4ff',
                        default     => '#f0fdf4',
                    };
                    $tipoBorder = match($r->tipo ?? 'repuesto') {
                        'mano_obra' => '#93c5fd',
                        'tercero'   => '#e879f9',
                        default     => '#86efac',
                    };
                    $tipoIcon = match($r->tipo ?? 'repuesto') {
                        'mano_obra' => '🔧',
                        'tercero'   => '🏢',
                        default     => '🔩',
                    };
                @endphp

                @if($editandoId === $r->id)
                {{-- ─ Modo edición ─ --}}
                <div style="background:white;border:2px solid #0f766e;border-radius:10px;padding:10px 12px;margin-bottom:8px;">
                    <div style="display:flex;gap:6px;margin-bottom:8px;">
                        <select wire:model="editTipo" style="border:1.5px solid #d1d5db;border-radius:7px;padding:5px 8px;font-size:12px;flex:0 0 auto;">
                            <option value="repuesto">🔩 Repuesto</option>
                            <option value="mano_obra">🔧 Mano de obra</option>
                            <option value="tercero">🏢 Tercero</option>
                        </select>
                        <input wire:model="editDesc" type="text" placeholder="Descripción"
                            style="flex:1;border:1.5px solid #d1d5db;border-radius:7px;padding:5px 8px;font-size:12px;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:8px;">
                        <div>
                            <label style="font-size:10px;color:#6b7280;display:block;margin-bottom:2px;">Cantidad</label>
                            <input wire:model="editCant" type="number" step="0.001" min="0.001"
                                style="width:100%;border:1.5px solid #d1d5db;border-radius:7px;padding:5px 8px;font-size:13px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:10px;color:#6b7280;display:block;margin-bottom:2px;">Precio venta</label>
                            <input wire:model="editPrecio" type="number" step="1" min="0"
                                style="width:100%;border:1.5px solid #d1d5db;border-radius:7px;padding:5px 8px;font-size:13px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:10px;color:#6b7280;display:block;margin-bottom:2px;">Costo proveedor</label>
                            <input wire:model="editCosto" type="number" step="1" min="0"
                                style="width:100%;border:1.5px solid #d1d5db;border-radius:7px;padding:5px 8px;font-size:13px;box-sizing:border-box;">
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button wire:click="guardarItem" style="flex:1;background:#0f766e;color:white;border:none;border-radius:7px;padding:6px;font-size:12px;font-weight:700;cursor:pointer;">✓ Guardar</button>
                        <button wire:click="$set('editandoId',null)" style="flex:1;background:#e5e7eb;color:#374151;border:none;border-radius:7px;padding:6px;font-size:12px;font-weight:700;cursor:pointer;">Cancelar</button>
                        <button wire:click="quitarItem({{ $r->id }})" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;border-radius:7px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;">🗑</button>
                    </div>
                </div>
                @else
                {{-- ─ Modo normal ─ --}}
                <div style="background:{{ $tipoBg }};border:1.5px solid {{ $tipoBorder }};border-radius:10px;padding:8px 12px;margin-bottom:6px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:16px;flex-shrink:0;">{{ $tipoIcon }}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $r->descripcion ?: '(sin descripción)' }}
                        </div>
                        <div style="font-size:11px;color:#6b7280;">
                            {{ number_format($r->cantidad, $r->cantidad == floor($r->cantidad) ? 0 : 2) }} × ${{ number_format($r->precio_unitario, 0, ',', '.') }}
                            @if($r->tipo === 'tercero')
                                <span style="background:#e879f922;color:#7e22ce;border-radius:99px;padding:1px 6px;font-size:10px;font-weight:700;">NO afecta ventas</span>
                            @endif
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:14px;font-weight:800;color:#0f766e;">${{ number_format($r->subtotal, 0, ',', '.') }}</div>
                        <button wire:click="editarItem({{ $r->id }})" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:12px;padding:0;">✏️ editar</button>
                    </div>
                </div>
                @endif

                @empty
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    <div style="font-size:36px;">🔩</div>
                    <div style="font-size:13px;margin-top:8px;">Busca un producto o agrega manualmente.</div>
                </div>
                @endforelse
            </div>

            {{-- Totales + facturar --}}
            <div style="border-top:2px solid #d1fae5;padding:12px 14px;background:white;flex-shrink:0;">
                @php
                    $totalRepuestos  = $orden->repuestos->where('tipo','repuesto')->sum('subtotal');
                    $totalManoObra   = $orden->repuestos->where('tipo','mano_obra')->sum('subtotal');
                    $totalTerceros   = $orden->repuestos->where('tipo','tercero')->sum('subtotal');
                @endphp
                @if($totalRepuestos > 0 || $totalManoObra > 0 || $totalTerceros > 0)
                <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">
                    @if($totalRepuestos > 0)
                        <div style="display:flex;justify-content:space-between;"><span>🔩 Repuestos</span><span>${{ number_format($totalRepuestos, 0, ',', '.') }}</span></div>
                    @endif
                    @if($totalManoObra > 0)
                        <div style="display:flex;justify-content:space-between;"><span>🔧 Mano de obra</span><span>${{ number_format($totalManoObra, 0, ',', '.') }}</span></div>
                    @endif
                    @if($totalTerceros > 0)
                        <div style="display:flex;justify-content:space-between;color:#7e22ce;"><span>🏢 Terceros (no factura)</span><span>${{ number_format($totalTerceros, 0, ',', '.') }}</span></div>
                    @endif
                </div>
                @endif

                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <span style="font-size:13px;font-weight:600;color:#374151;">TOTAL</span>
                    <span style="font-size:24px;font-weight:900;color:#0f766e;">${{ number_format($total, 0, ',', '.') }}</span>
                </div>

                @if($orden->estado !== 'entregado' && $orden->estado !== 'cancelado')
                <button wire:click="abrirModalFacturar"
                    style="width:100%;background:#0f766e;color:white;border:none;border-radius:10px;padding:13px;font-size:16px;font-weight:700;cursor:pointer;">
                    💵 Facturar orden
                </button>
                @else
                <div style="text-align:center;background:#f0fdf4;border-radius:10px;padding:10px;font-size:13px;color:#16a34a;font-weight:700;">
                    ✅ Orden {{ $orden->estado === 'entregado' ? 'entregada y facturada' : 'cancelada' }}
                    @if($orden->factura_id)
                        · <a href="{{ route('factura.ver', $orden->factura_id) }}" target="_blank" style="color:#0f766e;">Ver comprobante</a>
                    @endif
                </div>
                @endif
            </div>
        </div>

    </div>{{-- fin body --}}

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('swal-error', (msg) => {
                const text = Array.isArray(msg) ? msg[0] : msg;
                Swal.fire({ icon: 'error', title: 'Error', text: text, confirmButtonColor: '#ef4444' });
            });
        });
    </script>

</div>
