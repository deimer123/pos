<div class="pos-cart-component" style="display: flex; flex-direction: column; {{ $mesaId ? 'min-height: 100%' : 'height: 100%; min-height: 0;' }}">
    

    <div class="pos-cart-header" style="padding: 1rem; border-bottom: 1px solid #ddd;">
        <div class="flex items-center gap-2">
            <input type="text"
                class="flex-1 text-base bg-gray-100 border border-gray-300 rounded-full px-4 py-2 text-gray-700 font-medium cursor-not-allowed"
                value="{{ $clienteSeleccionadoNombre ?? 'Nombre Cliente' }}" disabled />

            @if (auth()->user()->puedeVerBotonPos('buscar_cliente'))
            <button wire:click="abrirModalBuscarCliente"
                class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm transition">

                Buscar Cliente
            </button>
            @endif


            <div class="flex items-center gap-2">
                @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'taller', 'recepcion']) && auth()->user()->puedeVerBotonPos('caja'))
                    <div class="flex items-center gap-3">
                        @if ($cajaEstado === 'abierta')
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Caja abierta</span>
                            <button type="button" wire:click="cerrarCajaModal"
                                class="h-8 px-3 bg-red-600 text-white rounded text-sm">Cerrar caja</button>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">Caja cerrada</span>
                            <button wire:click="abrirCajaModal"
                                class="h-8 px-3 bg-indigo-600 text-white rounded text-sm">Abrir caja</button>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>

    @if ($mostrarModalCartera)
        <div wire:key="cartera-modal-{{ $carteraRefreshKey }}"
            class="pos-cartera-overlay fixed inset-0 z-[99999] flex items-center justify-center">
            
            <div class="absolute inset-0 bg-black/50" wire:click="$set('mostrarModalCartera', false)"></div>

            
            <div class="pos-cartera-dialog relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                style="width:1000px; max-width:95vw; height:560px;">
                
                
                <div class="pos-cartera-header px-4 py-2 border-b flex items-center justify-between shrink-0">
                    <h3 class="text-base font-semibold">Cartera / Cuentas por cobrar</h3>
                    <div class="flex items-center gap-4" style="gap:12px;">
                        <button type="button"
                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition"
                            wire:click="abrirHistorial">Historial</button>

                        <button
                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition"
                            wire:click="$set('mostrarModalCartera', false)">Cerrar</button>
                    </div>
                </div>


                
                <div class="pos-cartera-body flex-1 overflow-hidden px-4 py-3">
                    <div class="pos-cartera-grid flex h-full gap-4 overflow-hidden">

                        
                        <div class="pos-cartera-clients shrink-0 w-[380px] flex flex-col" style="height: 480px;">
                            
                            <div class="sticky top-0 z-10 bg-white pb-2">
                                <input type="text" wire:model.debounce.500ms="carteraBuscar"
                                    placeholder="Buscar cliente" class="w-full border rounded-md px-3 py-2 text-sm">
                            </div>

                            
                            <div class="flex-1 overflow-y-auto overflow-x-hidden border rounded-md divide-y pr-2 -mr-2"
                                style="min-height:0; max-height:430px;">
                                @forelse($carteraClientes as $c)
                                    <div class="p-2 hover:bg-gray-50 cursor-pointer flex items-center justify-between"
                                        wire:click="seleccionarClienteCartera({{ $c['id'] }})">
                                        <div class="min-w-0">
                                            <div class="font-medium text-sm truncate">{{ $c['nombre'] }}</div>
                                            <div class="text-xs text-gray-600">
                                                Facturas: {{ $c['facturas'] }} | Vence max: {{ $c['max_venc'] ?? '-' }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold">
                                                ${{ number_format($c['saldo'], 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-3 text-sm text-gray-500">No hay clientes con cartera.</div>
                                @endforelse
                            </div>
                        </div>

                        
                        <div class="pos-cartera-invoices min-w-0 flex-1 flex flex-col overflow-hidden">
                            @if ($carteraClienteId)
                                <div class="flex items-center justify-between mb-2 shrink-0">
                                    <div class="text-sm text-gray-700">
                                        Total cliente: <b>${{ number_format($carteraTotalCliente, 0, ',', '.') }}</b>
                                    </div>
                                    <div class="text-xs text-gray-500">Selecciona una factura para ver o abonar.</div>
                                </div>

                                @if ($cargandoFacturas)
                                    <div class="flex-1 flex items-center justify-center text-gray-500">
                                        Cargando facturas...
                                    </div>
                                @else
                                    <div class="flex-1 overflow-y-auto overflow-x-hidden border rounded-md pr-2 -mr-2"
                                        style="min-height:0; max-height:430px;">
                                        <table class="w-full text-sm border border-gray-300">
                                            <thead class="bg-gray-100 sticky top-0 border-b border-gray-300">
                                                <tr class="text-left">
                                                    <th class="px-2 py-2 border border-gray-300">#</th>
                                                    <th class="px-2 py-2 border border-gray-300">Fecha</th>
                                                    <th class="px-2 py-2 border border-gray-300 text-right">Total</th>
                                                    <th class="px-2 py-2 border border-gray-300 text-right">Devuelto
                                                    </th>
                                                    <th class="px-2 py-2 border border-gray-300 text-right">Pagado</th>
                                                    <th class="px-2 py-2 border border-gray-300 text-right">Vence</th>
                                                    <th class="px-2 py-2 border border-gray-300">Estado</th>
                                                    <th class="px-2 py-2 border border-gray-300">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y border-t border-gray-300"
                                                wire:key="tbody-facturas-{{ $carteraClienteId }}">
                                                @forelse($carteraFacturas as $f)
                                                    <tr wire:key="row-fac-{{ $f['id'] }}">
                                                        <td class="px-2 py-2 border border-gray-300">
                                                            {{ $f['numero_visual'] ?? ('SAL-' . str_pad((string) $f['id'], 6, '0', STR_PAD_LEFT)) }}</td>
                                                        <td class="px-2 py-2 border border-gray-300">
                                                            {{ \Carbon\Carbon::parse($f['fecha'])->format('Y-m-d') }}
                                                        </td>
                                                        <td class="px-2 py-2 text-right border border-gray-300">
                                                            ${{ number_format($f['total'], 0, ',', '.') }}</td>
                                                        <td class="px-2 py-2 text-right border border-gray-300">
                                                            ${{ number_format($f['devuelto'], 0, ',', '.') }}</td>
                                                        <td class="px-2 py-2 text-right border border-gray-300">
                                                            ${{ number_format($f['pagado'], 0, ',', '.') }}</td>
                                                        <td
                                                            class="px-2 py-2 text-right font-bold text-indigo-700 border border-gray-300">
                                                            ${{ number_format($f['vence'], 0, ',', '.') }}</td>
                                                        <td class="px-2 py-2 border border-gray-300">
                                                            <span
                                                                class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-700">{{ $f['estado'] }}</span>
                                                        </td>
                                                        <td class="px-2 py-2 border border-gray-300">
                                                            <div class="flex items-center gap-3" style="gap:8px; flex-wrap:nowrap;">
                                                                <button type="button"
                                                                    wire:key="ver-{{ $f['id'] }}"
                                                                    wire:click.stop="verFacturaEnModal({{ $f['id'] }})"
                                                                    class="px-3 py-1 text-xs rounded border hover:bg-gray-50 rounded-full shadow-sm transition">
                                                                    Ver
                                                                </button>

                                                                <button type="button"
                                                                    wire:key="abo-{{ $f['id'] }}"
                                                                    wire:click.stop="abrirAbono({{ $f['id'] }}, {{ (int) $f['vence'] }})"
                                                                    class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition">
                                                                    Abonar
                                                                </button>

                                                                
                                                                <button type="button"
                                                                    onclick="window.posAbrirPagoCartera(this, {{ $f['id'] }}, {{ (int) $f['vence'] }}); return false;"
                                                                    class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition">
                                                                    Pagar
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8"
                                                            class="px-2 py-8 text-center text-sm text-gray-500">
                                                            No hay facturas pendientes.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @else
                                <div class="flex-1 border rounded-md grid place-items-center text-sm text-gray-500">
                                    Selecciona un cliente a la izquierda.
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($mostrarModalHistorial)
        <div wire:key="historial-modal-{{ $historialRefreshKey }}"
            class="pos-cartera-overlay fixed inset-0 z-[100000] flex items-center justify-center">
            
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarHistorial"></div>

            <div class="pos-cartera-dialog relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                style="width:1000px; max-width:95vw; height:560px;">
                
                <div class="pos-cartera-header px-4 py-2 border-b flex items-center justify-between shrink-0">
                    <h3 class="text-base font-semibold">Historial de facturas pagadas</h3>
                    <button
                        class="px-3 py-1.5 text-xs rounded bg-red-500 text-white hover:bg-red-600 rounded-full shadow-sm transition"
                        wire:click="cerrarHistorial">Cerrar</button>
                </div>

                
                <div class="pos-cartera-body px-4 pt-3 pb-2 border-b">
                    <div class="flex items-end gap-3 flex-wrap">
                        
                        <label class="flex flex-col gap-1 w-[170px] shrink-0">
                            <span class="text-[11px] text-gray-500">Desde</span>
                            <input type="date" class="h-8 text-sm px-2 border rounded-md"
                                wire:model.live="histDesde">
                        </label>

                        
                        <label class="flex flex-col gap-1 w-[170px] shrink-0">
                            <span class="text-[11px] text-gray-500">Hasta</span>
                            <input type="date" class="h-8 text-sm px-2 border rounded-md"
                                wire:model.live="histHasta">
                        </label>

                        
                        <label class="flex flex-col gap-1 flex-1 min-w-[240px]">
                            <span class="text-[11px] text-gray-500">Buscar (cliente o #)</span>
                            <input type="text" placeholder="Ej: Juan / 12345"
                                class="h-8 text-sm px-2 border rounded-md" wire:model.debounce.500ms="histBuscar">
                        </label>
                    </div>

                    
                    <div class="mt-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded" wire:model.live="histSoloCliente">
                            <span>Solo cliente seleccionado</span>
                        </label>
                    </div>
                </div>

                
                <div class="pos-cartera-body px-4 py-2 text-sm text-gray-700 flex items-center justify-between">
                    <div>
                        Registros: <b>{{ $historialCount }}</b>
                    </div>
                    <div>
                        Total pagado: <b>${{ number_format($historialTotal, 0, ',', '.') }}</b>
                    </div>
                </div>

                
                <div class="pos-cartera-body flex-1 overflow-y-auto overflow-x-hidden px-4 pb-4">
                    <table class="w-full text-sm border border-gray-300">
                        <thead class="bg-gray-100 sticky top-0 border-b border-gray-300">
                            <tr class="text-left">
                                <th class="px-2 py-2 border border-gray-300">#</th>
                                <th class="px-2 py-2 border border-gray-300 whitespace-nowrap">Fecha pago</th>
                                <th class="px-2 py-2 border border-gray-300">Cliente</th>
                                <th class="px-2 py-2 text-right border border-gray-300">Total</th>
                                <th class="px-2 py-2 text-right border border-gray-300">Saldo</th>
                                <th class="px-2 py-2 border border-gray-300">Estado</th>
                                <th class="px-2 py-2 border border-gray-300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y border-t border-gray-300" wire:key="tbody-historial">
                            @forelse($historialFacturas as $h)
                                <tr wire:key="hist-{{ $h['id'] }}">
                                    <td class="px-2 py-2 border border-gray-300">{{ $h['numero_visual'] ?? ('SAL-' . str_pad((string) $h['id'], 6, '0', STR_PAD_LEFT)) }}</td>
                                    <td class="px-2 py-2 border border-gray-300 whitespace-nowrap text-xs">
                                        {{ $h['fecha_pago'] }}
                                    </td>
                                    <td class="px-2 py-2 border border-gray-300">{{ $h['cliente'] }}</td>
                                    <td class="px-2 py-2 text-right border border-gray-300">
                                        ${{ number_format($h['total'], 0, ',', '.') }}</td>
                                    <td class="px-2 py-2 text-right border border-gray-300">
                                        ${{ number_format($h['saldo'], 0, ',', '.') }}</td>
                                    <td class="px-2 py-2 border border-gray-300">
                                        <span
                                            class="px-2 py-0.5 text-xs rounded-full bg-green-50 text-green-700">{{ $h['estado'] }}</span>
                                    </td>
                                    <td class="px-2 py-2 border border-gray-300">
                                        <button type="button"
                                            class="px-3 py-1 text-xs rounded border hover:bg-gray-50 rounded-full shadow-sm transition"
                                            wire:click.stop="verFacturaEnModal({{ $h['id'] }})">
                                            Ver
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-2 py-8 text-center text-sm text-gray-500">
                                        No hay facturas pagadas en el rango seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif


    
    @if ($mostrarModalFactura && $verFacturaId)
        <div class="fixed inset-0 z-[100001] flex items-center justify-center"
            wire:key="modal-fac-{{ $verFacturaId }}">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarFacturaModal"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-[480px] max-w-[95vw] h-[78vh] overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 border-b">
                    <h4 class="text-sm font-semibold">{{ 'SAL-' . str_pad((string) $verFacturaId, 6, '0', STR_PAD_LEFT) }} (solo lectura)</h4>
                    <button class="px-3 py-1 rounded-full bg-red-500 hover:bg-red-600 text-white text-xs font-bold" wire:click="cerrarFacturaModal">Cerrar</button>
                </div>

                <iframe key="iframe-{{ $verFacturaId }}" src="{{ route('factura.ver', $verFacturaId) }}"
                    class="w-full" style="height: calc(78vh - 82px);" frameborder="0"></iframe>

                <div class="px-3 py-2 border-t flex items-center justify-end gap-2">
                    <button type="button"
                        class="px-3 h-8 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm"
                        wire:click="imprimirFacturaActual">
                        Imprimir
                    </button>

                    <button class="px-3 h-8 rounded-full bg-gray-800 hover:bg-gray-900 text-white text-sm"
                        wire:click="cerrarFacturaModal">Cerrar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($mostrarModalAbono)
        <div class="fixed inset-0 z-[75] flex items-center justify-center px-3" style="z-index:2147482500;">
            <div class="absolute inset-0 bg-black/55" style="background:rgba(0,0,0,.55);" wire:click="cerrarAbono"></div>

            <div class="relative bg-white shadow-2xl"
                style="width:512px;max-width:94vw;border-radius:4px;padding:42px 50px 32px 50px;"
                x-data="{ medio: @entangle('abonoMedio').live }">
                <div style="width:88px;height:88px;border:4px solid #8bb0bf;border-radius:999px;margin:0 auto 30px auto;display:flex;align-items:center;justify-content:center;color:#7fa8ba;font-size:58px;line-height:1;font-weight:300;">
                    ?
                </div>

                <div style="text-align:center;margin-bottom:26px;">
                    <h3 style="font-size:30px;line-height:1.2;font-weight:700;color:#555;margin:0 0 14px 0;">Abonar a factura</h3>
                    <p style="font-size:14px;color:#6b7280;margin:0;">{{ 'SAL-' . str_pad((string) $abonoFacturaId, 6, '0', STR_PAD_LEFT) }} - Saldo actual <b>$ {{ number_format($abonoVence, 0, ',', '.') }}</b></p>
                </div>

                <div style="width:340px;max-width:100%;margin:0 auto;text-align:left;display:flex;flex-direction:column;gap:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#4b5563;margin-bottom:5px;">Monto a abonar</label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px;color:#4b5563;">$</span>
                            <input id="inp_abono_monto" type="number" min="0" max="{{ (int) $abonoVence }}"
                                step="1"
                                style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 10px 4px 26px;font-size:14px;text-align:right;outline:none;"
                                value="{{ (int) $abonoMonto }}"
                                oninput="const max=Number(this.max||0); const val=Number(this.value||0); if (max && val > max) this.value = max;" />
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#4b5563;margin-bottom:5px;">Medio de pago</label>
                        <select
                            style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 10px;font-size:14px;background:white;outline:none;"
                            x-model="medio" wire:model.live="abonoMedio"
                            @change="
                                if (medio === 'transferencia') { $refs.obs?.setAttribute('required','required'); $refs.obs?.focus(); }
                                else { $refs.obs?.removeAttribute('required'); }
                            ">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div x-show="medio === 'transferencia'" x-cloak>
                        <label style="display:block;font-size:12px;font-weight:700;color:#4b5563;margin-bottom:5px;">Observacion de transferencia</label>
                        <input x-ref="obs" id="abono_transfer_obs" type="text"
                            style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 10px;font-size:14px;outline:none;"
                            wire:model.lazy="abonoTransferObs" placeholder="Ej: banco o cuenta"
                            :required="medio === 'transferencia'" />
                        @error('abonoTransferObs')
                            <div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div style="margin-top:38px;display:flex;justify-content:center;gap:10px;align-items:center;">
                    <button type="button"
                        style="height:44px;min-width:96px;border:0;border-radius:4px;background:#6b7280;color:white;font-size:16px;font-weight:700;padding:0 18px;box-shadow:none;cursor:pointer;"
                        @click.prevent="$wire.cerrarAbono()">
                        Cancelar
                    </button>

                    <button type="button"
                        style="height:44px;min-width:96px;border:0;border-radius:4px;background:#6d5df2;color:white;font-size:16px;font-weight:700;padding:0 18px;box-shadow:none;cursor:pointer;"
                        @click.prevent="
                            const elMonto = document.getElementById('inp_abono_monto');
                            let m = (elMonto && !Number.isNaN(elMonto.valueAsNumber)) ? Math.trunc(elMonto.valueAsNumber) : 0;
                            const maxSaldo = {{ (int) $abonoVence }};
                            if (m < 0) m = 0;
                            if (m > maxSaldo) { Swal.fire('Monto invalido', 'El abono no puede ser mayor al saldo pendiente.', 'warning'); elMonto?.focus(); return; }

                            if (medio === 'transferencia') {
                                const obsEl = $refs.obs;
                                const obsVal = (obsEl?.value || '').trim();
                                if (!obsVal) {
                                    obsEl?.focus();
                                    Swal.fire('Falta observacion', 'Escribe banco o cuenta para la transferencia.', 'warning');
                                    return;
                                }
                            }

                            Swal.fire({
                                title: 'Confirmar abono',
                                html: 'Se abonara <b>$ ' + m.toLocaleString('es-CO') + '</b> (' + medio + ').',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Guardar',
                                cancelButtonText: 'Cancelar',
                                reverseButtons: true
                            }).then(function(res){
                                if (res.isConfirmed) { $wire.confirmarAbonoConValor(m); }
                            });
                        "
                        wire:loading.attr="disabled">
                        Guardar
                    </button>
                </div>
                </div>
            </div>
        </div>
    @endif



    
    {{-- TALLER BANNER --}}
    @if($tallerOrdenId)
        @php
            $tallerOrden = \App\Models\TallerOrden::find($tallerOrdenId);
        @endphp
        @if($tallerOrden)
        <div style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;padding:10px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap;">
            <div style="font-size:20px;">🔧</div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:11px;font-weight:800;letter-spacing:.06em;opacity:.8;">ORDEN TALLER #{{ str_pad($tallerOrden->numero_orden,4,'0',STR_PAD_LEFT) }}</div>
                <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $tallerOrden->placa }} · {{ $tallerOrden->cliente_nombre }}
                </div>
                @if($tallerOrden->marca || $tallerOrden->modelo)
                <div style="font-size:11px;opacity:.85;">{{ $tallerOrden->marca }} {{ $tallerOrden->modelo }}</div>
                @endif
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                <label x-data style="display:flex;align-items:center;gap:4px;cursor:pointer;background:rgba(255,255,255,.15);border-radius:8px;padding:4px 8px;font-size:11px;font-weight:700;"
                    x-on:livewire-upload-finish="$wire.subirFotoTaller()">
                    📷
                    <input type="file" wire:model="tallerFotoTemp" accept="image/*" style="display:none;">
                    <span>Foto</span>
                </label>
                <button wire:click="limpiarTaller" onclick="return confirm('¿Desvincular orden de taller del carrito?')"
                    style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:8px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                    ✕ Desvincular
                </button>
            </div>
            @if($tallerOrden->fotos && count($tallerOrden->fotos))
            <div style="width:100%;display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                @foreach($tallerOrden->fotos as $fi => $foto)
                <div style="position:relative;">
                    <img src="{{ Storage::url($foto) }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:2px solid rgba(255,255,255,.4);">
                    <button wire:click="eliminarFotoTaller({{ $fi }})"
                        style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:16px;height:16px;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>
                @endforeach
            </div>
            @endif
            <div wire:loading wire:target="tallerFotoTemp" style="width:100%;font-size:11px;opacity:.8;">Subiendo foto...</div>
        </div>
        @endif
    @endif

    {{-- HOTEL BANNER --}}
    @if($hotelReservaId)
        @php
            $hotelReserva = \App\Models\HotelReserva::with('habitacion')->find($hotelReservaId);
        @endphp
        @if($hotelReserva)
        <div style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;padding:10px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0;flex-wrap:wrap;">
            <div style="font-size:20px;">🏨</div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:11px;font-weight:800;letter-spacing:.06em;opacity:.8;">RESERVA #{{ str_pad($hotelReserva->numero_reserva,4,'0',STR_PAD_LEFT) }} · HABITACIÓN {{ $hotelReserva->habitacion->numero ?? '?' }}</div>
                <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $hotelReserva->huesped_nombre }}
                </div>
                @if($hotelAbonoMonto > 0)
                <div style="font-size:11px;opacity:.95;margin-top:2px;">
                    💰 Abono ya pagado: ${{ number_format($hotelAbonoMonto, 0, ',', '.') }} ({{ $hotelAbonoMedioPago }})
                    · Saldo a cobrar: ${{ number_format(max(0, ($totalGeneral ?? 0) - $hotelAbonoMonto), 0, ',', '.') }}
                </div>
                @endif
            </div>
        </div>
        @endif
    @endif

    {{-- CARRITO CARDS --}}
    <div class="pos-cart-table-scroll flex-1 min-h-0 overflow-y-auto overflow-x-hidden" style="background:#f1f5f9; padding:8px 6px; display:flex; flex-direction:column; gap:6px;">

        @forelse($carrito as $id => $item)
            @php
                $enviado = !empty($item['enviado_cocina']);
                $permiteDecimal = (bool) ($item['permite_decimal'] ?? ((int) ($item['id_unidad_de_medida'] ?? 1) !== 1));
                $stockUnidadCarrito = match ($item['vende_por'] ?? 'unidad') {
                    'peso' => 'kg', 'porcion' => 'porcion', 'litro' => 'lt', 'metro' => 'mt', 'hora' => 'hr',
                    default => ((bool) ($item['permite_decimal'] ?? false) ? 'kg' : 'und'),
                };
                $stockDecimalesCarrito = $stockUnidadCarrito === 'und' ? 0 : 2;
                if (!empty($item['porciones_receta'])) {
                    $stockValorCarrito  = (float) $item['porciones_receta']['porciones'];
                    $stockUnidadCarrito = $item['porciones_receta']['unidad'];
                    $stockDecimalesCarrito = 2;
                } else {
                    $stockValorCarrito = (float) ($item['existencias'] ?? 0);
                }
                $stockTooltipCarrito = number_format($stockValorCarrito, $stockDecimalesCarrito, ',', '.') . ' ' . $stockUnidadCarrito;
                $sinStock = $stockValorCarrito <= 0;
                $precioUnidad = match ($item['vende_por'] ?? 'unidad') {
                    'peso'    => 'x kg',
                    'porcion' => 'x porción',
                    'litro'   => 'x lt',
                    'metro'   => 'x mt',
                    'hora'    => 'x hr',
                    default   => ((bool) ($item['permite_decimal'] ?? false) ? 'x kg' : 'c/u'),
                };
            @endphp

            {{-- CARD --}}
            <div wire:key="cart-card-{{ $id }}"
                style="background:{{ $enviado ? '#f0fdf4' : 'white' }}; border-radius:12px; border:1px solid {{ $enviado ? '#86efac' : '#e2e8f0' }}; padding:10px 10px 8px 10px; display:flex; align-items:center; gap:8px; box-shadow:0 1px 4px rgba(0,0,0,.06); opacity:{{ $enviado ? '.85' : '1' }}; transition:box-shadow .15s;">

                {{-- Número / código --}}
                <div style="flex-shrink:0; width:34px; height:34px; border-radius:8px; background:{{ $enviado ? '#dcfce7' : '#eef2ff' }}; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:{{ $enviado ? '#16a34a' : '#4338ca' }};">
                    {{ $item['id_producto'] ?? '-' }}
                </div>

                {{-- Nombre + badges --}}
                <div style="flex:1; min-width:0;">
                    <button type="button"
                        title="{{ $item['nombre'] }} | Stock: {{ $stockTooltipCarrito }}"
                        @click="$dispatch('ver-nombre-carrito-mobile', { nombre: @js($item['nombre']), stock: @js($stockTooltipCarrito) })"
                        style="display:block; width:100%; text-align:left; background:none; border:none; padding:0; cursor:pointer;">
                        <div style="font-size:13px; font-weight:700; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2;">
                            {{ $item['nombre'] }}
                        </div>
                    </button>
                    <div style="display:flex; gap:4px; flex-wrap:wrap; margin-top:4px; align-items:center;">
                        {{-- Precio unitario con unidad correcta --}}
                        <span style="font-size:10px; color:#64748b; font-weight:600;">
                            ${{ number_format(round($item['nuevo_precio'] ?? ($item['precio'] ?? 0)), 0, ',', '.') }} {{ $precioUnidad }}
                        </span>
                        @if($enviado)
                            <span style="font-size:9px; background:#16a34a; color:white; border-radius:20px; padding:1px 7px; font-weight:700;">✓ Cocina</span>
                        @endif
                        @if(!empty($item['combo_activo']))
                            <span style="font-size:9px; background:#7c3aed; color:white; border-radius:20px; padding:1px 7px; font-weight:700;">🎁 {{ $item['combo_activo'] }}</span>
                        @endif
                    </div>
                </div>

                {{-- Cantidad --}}
                <div style="flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:2px;">
                    <span style="font-size:9px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.05em;">Cant.</span>
                    <input type="number"
                        min="{{ $permiteDecimal ? '0.01' : '1' }}"
                        step="{{ $permiteDecimal ? '0.01' : '1' }}"
                        inputmode="{{ $permiteDecimal ? 'decimal' : 'numeric' }}"
                        wire:model.lazy="carrito.{{ $id }}.cantidad"
                        wire:change="actualizarTotales"
                        wire:key="cantidad-{{ $id }}-{{ $item['cantidad'] }}"
                        {{ $enviado ? 'disabled' : '' }}
                        style="width:52px; height:32px; font-size:14px; font-weight:800; text-align:center; border:2px solid {{ $enviado ? '#86efac' : '#a5b4fc' }}; border-radius:8px; background:{{ $enviado ? '#dcfce7' : '#eef2ff' }}; color:{{ $enviado ? '#15803d' : '#3730a3' }}; padding:0 3px; outline:none;" />
                </div>

                {{-- Total --}}
                <div style="flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:2px; min-width:70px;">
                    <span style="font-size:9px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.05em;">Total</span>
                    <span style="font-size:15px; font-weight:900; color:{{ $enviado ? '#15803d' : '#0f766e' }}; white-space:nowrap; line-height:1;">
                        ${{ number_format($item['total'] ?? 0, 0, ',', '.') }}
                    </span>
                </div>

                {{-- Botones acción --}}
                @if(! $enviado)
                <div style="flex-shrink:0; display:flex; flex-direction:column; gap:4px; align-items:center;">
                    <button x-data
                        x-on:click="$wire.abrirModalRenombrar('{{ $item['uuid'] ?? $item['id_producto'] }}')"
                        title="Editar"
                        style="width:30px; height:30px; border-radius:8px; border:none; background:#e0e7ff; color:#4338ca; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 9.586-9.586z"/>
                        </svg>
                    </button>
                    <button x-data="{ uuid: @js($item['uuid'] ?? $item['id_producto']) }"
                        x-on:click="Swal.fire({ title:'Borrar?', icon:'warning', showCancelButton:true, confirmButtonText:'Si', cancelButtonText:'No' }).then(r=>{ if(r.isConfirmed){ $wire.eliminarDelCarrito(uuid) } })"
                        title="Eliminar"
                        style="width:30px; height:30px; border-radius:8px; border:none; background:#fee2e2; color:#dc2626; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2.25 2.25 0 0115.891 21H8.109a2.25 2.25 0 01-2.242-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/>
                        </svg>
                    </button>
                </div>
                @endif

            </div>
        @empty
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 16px; color:#94a3b8;">
                <div style="font-size:40px; margin-bottom:10px;">🛒</div>
                <div style="font-size:13px; font-weight:600;">Sin productos en la orden</div>
            </div>
        @endforelse

    </div>



    

    <div class="pos-cart-total-bar px-3 py-2 border-t bg-white flex items-center justify-between">
        @if($mesaId)
            {{-- Enviar cocina y Facturar: visibles siempre (desktop Y móvil) --}}
            <div class="flex gap-2 flex-shrink-0 items-center">
                {{-- Badge estado cocina --}}
                @if($ordenEstadoActual === 'en_preparacion')
                    <span style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:999px;padding:2px 10px;font-size:10px;font-weight:800;white-space:nowrap;">
                        🍳 En cocina
                        @if($ordenTipoPedido === 'domicilio') · 🛵 Dom @elseif($ordenTipoPedido === 'para_llevar') · 🥡 Llevar @endif
                        @if($ordenCostoEmpaque > 0) +${{ number_format($ordenCostoEmpaque,0,',','.') }} @endif
                    </span>
                @endif
                <button x-on:click="window.posMesaEnviarCocinaModal($wire.get('clienteSeleccionadoNombre') || '', $wire.get('ordenDomDireccion') || $wire.get('clienteDireccion') || '', $wire.get('ordenDomTelefono') || $wire.get('clienteTelefono') || '', $wire.get('ordenDomNombre') || '', $wire.get('ordenDomObservaciones') || '', $wire.get('ordenDomCostoDomicilio') || 0, $wire.get('ordenDomCostoDesechables') || 0, $wire.get('ordenEstadoActual') || 'abierta', $wire.get('ordenTipoPedido') || 'mesa', $wire.get('usaDomicilios') || false, $wire.get('esMesero') || false, $wire.get('esMesaDomicilio') || false)"
                    class="pos-mesa-total-btn"
                    style="background:#2563eb; color:white; border:none; border-radius:9999px; padding:0 14px; height:34px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">
                    📤 Enviar cocina
                </button>
                @if ((auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa')) && auth()->user()->puedeVerBotonPos('facturar'))
                <button onclick="window.Livewire.dispatch('mesa-facturar')"
                    class="pos-mesa-total-btn"
                    style="background:#16a34a; color:white; border:none; border-radius:9999px; padding:0 14px; height:34px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">
                    💳 Facturar
                </button>
                @endif
            </div>
        @else
        @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'taller', 'recepcion']) && auth()->user()->puedeVerBotonPos('facturar'))
            <button type="button" id="btn-abrir-facturar"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                wire:click="confirmarFacturar" wire:loading.attr="disabled" wire:target="confirmarFacturar">
                Facturar
            </button>
        @endif
        @endif
        <div class="text-right">
            <span class="text-indigo-700 font-bold text-sm align-middle">TOTAL:</span>
            <span class="text-gray-900 font-extrabold text-2xl sm:text-3xl align-middle">
                ${{ number_format($totalGeneral ?? 0, 0, ',', '.') }}
            </span>
        </div>
    </div>

    <div class="pos-cart-observation mb-2 w-full">
        <textarea
            class="form-control w-full rounded border border-gray-300 px-3 py-1 resize-y min-h-[36px] focus:outline-none focus:ring-2 focus:ring-indigo-500"
            id="identificadorPrefactura" wire:model="observacionesPrefactura"
            wire:key="observacion-input-{{ $observacionKey }}"
            placeholder="{{ $mesaId ? 'Observaciones para cocina...' : 'Ingrese una observacion para esta factura' }}"
            rows="1"></textarea>
    </div>

    <div class="pos-desktop-cart-actions flex items-center justify-between">
        @if(! $mesaId)
        @php $usaTallerPos = (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())->value('usa_taller'); @endphp
        @if($usaTallerPos)
        {{-- POS con Taller: sin Cartera ni Guardar (prefactura). Solo Ingresar/Ver lobby,
             Guardar orden y Limpiar quedan sueltos; el resto se agrupa en "Más acciones". --}}
        <div style="display:flex; flex-wrap:wrap; gap:4px; width:100%; align-items:center;" x-data="{ masAcciones: false }" @click.outside="masAcciones = false">

            @if($tallerOrdenId)
            <button
                x-on:click="Swal.fire({title:'¿Ir al lobby?',text:'Se guardarán los productos actuales en la orden antes de salir.',icon:'question',showCancelButton:true,confirmButtonText:'Guardar y salir',cancelButtonText:'Cancelar',confirmButtonColor:'#0f766e'}).then(r=>{if(r.isConfirmed){$wire.salirALobbyTaller();}})"
                style="flex:1 1 0; min-width:110px; background:#0d9488;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                🔧 Ver lobby
            </button>
            @else
            <button onclick="abrirIngresoTaller(@js($clienteSeleccionadoNombre ?? ''), @js($clienteTelefono ?? ''))"
                style="flex:1 1 0; min-width:110px; background:#0f766e;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                🔧 Ingresar
            </button>
            @endif

            @if($tallerOrdenId)
            <button
                x-on:click="Swal.fire({title:'💾 Guardar orden taller',text:'Se guardan los productos en la orden. Puedes reabrirla desde el panel Taller.',icon:'question',showCancelButton:true,confirmButtonText:'Guardar',cancelButtonText:'Cancelar',confirmButtonColor:'#0f766e'}).then(r=>{if(r.isConfirmed){$wire.guardarOrdenTaller();}})"
                style="flex:1 1 0; min-width:120px; background:#0f766e;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                💾 Guardar orden
            </button>
            @endif

            <button
                x-on:click="
                    const hayTaller = !! $wire.get('tallerOrdenId');
                    Swal.fire({
                        title: hayTaller ? '¿Cancelar orden de taller?' : '¿Vaciar carrito?',
                        text: hayTaller ? 'Se eliminarán los productos Y la orden del lobby.' : 'Se eliminarán todos los productos.',
                        icon:'warning', showCancelButton:true,
                        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar',
                        confirmButtonColor:'#dc2626'
                    }).then(r=>{ if(r.isConfirmed){ $wire.limpiarCarrito(); } })"
                style="flex:1 1 0; min-width:90px; background:#dc2626;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Limpiar
            </button>

            <button type="button" @click="masAcciones = !masAcciones"
                style="flex:1 1 0; min-width:130px; background:#334155;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;"
                x-text="masAcciones ? 'Ocultar acciones ▴' : 'Más acciones ▾'">
                Más acciones ▾
            </button>

            <template x-if="masAcciones">
            <button type="button"
                x-on:click="
                    masAcciones = false;
                    if(Object.keys($wire.get('carrito') ?? {}).length===0){Swal.fire({icon:'warning',title:'Carrito vacío',text:'Agregue productos primero.'});}else{$wire.abrirModalEditar();}"
                style="flex:1 1 0; min-width:90px; background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Editar
            </button>
            </template>

            <template x-if="masAcciones">
            <button type="button" x-on:click="masAcciones = false; $wire.abrirModalBuscarCliente();"
                style="flex:1 1 0; min-width:110px; background:#2563eb;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                🔍 Cliente
            </button>
            </template>

            <template x-if="masAcciones">
            <button type="button" x-on:click="masAcciones = false; $wire.abrirModalCrearCliente();"
                style="flex:1 1 0; min-width:90px; background:#7c3aed;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                + Cliente
            </button>
            </template>

            @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'taller', 'recepcion']) && $cajaEstado === 'abierta')
            <template x-if="masAcciones">
            <button type="button" x-on:click="masAcciones = false; $wire.abrirMovimientoCajaModal('salida');"
                style="flex:1 1 0; min-width:110px; background:#0891b2;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Entrada/Salida
            </button>
            </template>
            @endif

            <template x-if="masAcciones">
            <button type="button" x-on:click="masAcciones = false; $wire.verPrefacturas();"
                style="flex:1 1 0; min-width:80px; background:#475569;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Ver
            </button>
            </template>

        </div>
        @else
        {{-- POS base (sin taller) --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(90px, 1fr)); gap:4px; width:100%; align-items:center;">

            @if (auth()->user()->puedeVerBotonPos('editar'))
            <button
                x-on:click="if(Object.keys($wire.get('carrito') ?? {}).length===0){Swal.fire({icon:'warning',title:'Carrito vacío',text:'Agregue productos primero.'});}else{$wire.abrirModalEditar();}"
                style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Editar
            </button>
            @endif

            @if (auth()->user()->puedeVerBotonPos('mas_cliente'))
            <button wire:click="abrirModalCrearCliente"
                style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                + Cliente
            </button>
            @endif

            @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'recepcion']))
                @if (auth()->user()->puedeVerBotonPos('cartera'))
                <button type="button" wire:click="abrirModalCartera"
                    style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                    Cartera
                </button>
                @endif

                @if ($cajaEstado === 'abierta' && auth()->user()->puedeVerBotonPos('entrada_salida'))
                <button type="button" wire:click="abrirMovimientoCajaModal('salida')"
                    style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                    Entrada/Salida
                </button>
                @endif
            @endif

            @if (auth()->user()->puedeVerBotonPos('limpiar'))
            <button
                x-on:click="
                    const hayHotel = !! $wire.get('hotelReservaId');
                    Swal.fire({
                        title: hayHotel ? '¿Anular esta reserva?' : '¿Vaciar carrito?',
                        text: hayHotel ? 'Se eliminará la reserva y sus consumos, y la habitación quedará libre, como si nunca se hubiera hospedado.' : 'Se eliminarán todos los productos.',
                        icon:'warning', showCancelButton:true,
                        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar',
                        confirmButtonColor:'#dc2626'
                    }).then(r=>{ if(r.isConfirmed){ $wire.limpiarCarrito(); } })"
                style="width:100%;text-align:center;background:#dc2626;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Limpiar
            </button>
            @endif

            @if(! $hotelReservaId && auth()->user()->puedeVerBotonPos('guardar'))
            <button type="button" wire:click="confirmarGuardarPrefactura"
                style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Guardar
            </button>
            @endif

            @if (auth()->user()->puedeVerBotonPos('ver'))
            <button wire:click="verPrefacturas"
                style="width:100%;text-align:center;background:#4f46e5;color:#fff;border:none;border-radius:999px;padding:0 12px;height:30px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                Ver
            </button>
            @endif

        </div>
        @endif
        @endif

        <div class="flex gap-2">

            @if($mesaId)
            @php $esMesero = auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor']); @endphp
            {{-- En modo mesa: botones visibles en desktop e iPad, ocultos en móvil (menú ☰) --}}
            @if(! $esMesero)
            <button
                x-on:click="
                    if (($wire.get('carrito') ?? []).length === 0) {
                      Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de editar.'});
                    } else { $wire.abrirModalEditar(); }
                "
                class="pos-cart-secondary-action pos-hide-mobile bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                Editar
            </button>
            <button wire:click="abrirModalCrearCliente"
                class="pos-cart-secondary-action pos-btn-texto-doble pos-hide-mobile bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                + Crear Cliente
            </button>
            @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
            @if ($cajaEstado === 'abierta')
            <button type="button"
                class="pos-cart-secondary-action pos-btn-texto-doble pos-hide-mobile bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                wire:click="abrirMovimientoCajaModal('salida')">
                Entrada / salida
            </button>
            @endif
            <button type="button"
                class="pos-cart-secondary-action pos-hide-mobile bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                wire:click="abrirModalCartera">
                Cartera
            </button>
            @endif
            <button
                x-on:click="Swal.fire({
                          title: '¿Liberar mesa?', text: 'Se cancelará la comanda y se liberará la mesa.',
                          icon: 'warning', showCancelButton: true,
                          confirmButtonColor: '#dc2626', confirmButtonText: 'Sí, liberar', cancelButtonText: 'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-liberar'); }})"
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full"
                style="background:#dc2626;">
                🔓 Liberar
            </button>
            <button
                x-on:click="Swal.fire({
                          title: '¿Poner en espera?', text: 'La cuenta se guarda y la mesa queda libre.',
                          icon: 'question', showCancelButton: true,
                          confirmButtonColor: '#d97706', confirmButtonText: 'Sí, en espera', cancelButtonText: 'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-en-espera'); }})"
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full"
                style="background:#d97706;">
                ⏸ Espera
            </button>
            <button
                x-on:click="
                    if (Object.keys($wire.get('carrito') ?? {}).length === 0) {
                        Swal.fire({icon:'warning', title:'Mesa sin productos', text:'Debe haber una mesa activa con productos para imprimir la cuenta.'});
                    } else {
                        window.open('/pos/mesa/{{ $mesaId }}/cuenta', '_blank', 'width=420,height=680');
                    }
                "
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full"
                style="background:#374151;">
                🖨️ Cuenta
            </button>
            <button wire:click="verPrefacturas"
                class="pos-cart-secondary-action pos-hide-mobile bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                Ver
            </button>
            @endif {{-- fin !$esMesero --}}
            @else
            {{-- Sin mesa y sin taller: Limpiar --}}
            @endif
        </div>


    </div>{{-- /pos-desktop-cart-actions --}}

    @php
        $esMeseroPuroMenu = auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor']);
        $usaTallerPosMobile = (! $mesaId) && (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())->value('usa_taller');
    @endphp
    @if(! $usaTallerPosMobile)
    <div class="pos-cart-mobile-more" x-data="{ open: false }" wire:key="mobile-actions-root-{{ $cajaEstado }}">
        <button type="button" class="pos-cart-mobile-more-button" @click="open = !open">
                Acciones
            </button>

            <div class="pos-cart-mobile-more-menu" x-show="open" x-cloak @click.stop @click.outside="open = false" wire:key="mobile-actions-menu-{{ $cajaEstado }}">
                @if(! $mesaId || ! $esMeseroPuroMenu)
                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-edit" wire:key="mobile-action-editar"
                    @click.prevent.stop="
                        open = false;
                        if (($wire.get('carrito') ?? []).length === 0) {
                          Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de editar.'});
                        } else { $wire.abrirModalEditar(); }
                    ">
                    Editar
                </button>

                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-search-client" wire:key="mobile-action-buscar-cliente" @click.prevent.stop="open = false; $wire.abrirModalBuscarCliente();">
                    Buscar Cliente
                </button>

                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-create-client" wire:key="mobile-action-crear-cliente" @click.prevent.stop="open = false; $wire.abrirModalCrearCliente();">
                    Crear Cliente
                </button>
                @endif

                @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'recepcion']))
                    @if ($cajaEstado === 'abierta')
                        <button type="button" class="pos-cart-menu-item pos-cart-menu-item-cash-move" wire:key="mobile-action-movimiento-caja" @click.prevent.stop="open = false; $wire.abrirMovimientoCajaModal('salida');">
                            Entrada / salida
                        </button>
                    @else
                        <button type="button" class="pos-cart-menu-item pos-cart-menu-item-open-cash" wire:key="mobile-action-abrir-caja" @click.prevent.stop="open = false; $wire.abrirCajaModal();">
                            Abrir caja
                        </button>
                    @endif

                    <button type="button" class="pos-cart-menu-item pos-cart-menu-item-wallet" wire:key="mobile-action-cartera" @click.prevent.stop="open = false; $wire.abrirModalCartera();">
                        Cartera
                    </button>
                @endif

                @if(! $mesaId && ! $tallerOrdenId && ! $hotelReservaId)
                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-save" wire:key="mobile-action-guardar" @click.prevent.stop="open = false; $wire.confirmarGuardarPrefactura();">
                    Guardar
                </button>
                @endif

                @if(! $mesaId || ! $esMeseroPuroMenu)
                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-view" wire:key="mobile-action-ver" @click.prevent.stop="open = false; $wire.verPrefacturas();">
                    Ver
                </button>
                @endif
            </div>
        </div>
    @endif

    <div x-data="{ nombreCarritoMobile: null, stockCarritoMobile: null }" @ver-nombre-carrito-mobile.window="nombreCarritoMobile = $event.detail.nombre; stockCarritoMobile = $event.detail.stock">
        <div x-show="nombreCarritoMobile" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" x-transition>
            <div @click.outside="nombreCarritoMobile = null" class="w-full max-w-sm rounded-xl bg-white p-4 shadow-lg">
                <div class="text-center text-sm font-semibold text-slate-800 break-words" x-text="nombreCarritoMobile"></div>
                <div x-show="stockCarritoMobile" class="mt-3 flex justify-center">
                    <div class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 shadow-sm">
                        Stock: <span class="ml-1" x-text="stockCarritoMobile"></span>
                    </div>
                </div>
                <div class="mt-4 flex justify-center">
                    <button @click="nombreCarritoMobile = null; stockCarritoMobile = null"
                        class="rounded-full bg-indigo-600 px-4 py-2 text-sm text-white shadow hover:bg-indigo-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
@if ($mostrarModalCrearCliente)
        <div class="fixed inset-0 flex items-center justify-center bg-black/50" style="z-index: 2147482800;">
            <div class="bg-white rounded-xl shadow-xl border overflow-hidden flex flex-col" style="width: 90%; max-width: 720px; max-height: 92vh;">
                <div class="pos-cartera-header px-4 py-3 border-b flex items-center justify-between shrink-0">
                    <h2 class="text-base font-semibold">Crear Cliente</h2>
                </div>
                <div class="p-6 overflow-y-auto" style="max-height: calc(92vh - 58px);">


                
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-indigo-600 mb-2">Datos personales</h3>
                    <div class="flex gap-4 mb-2">
                        <div style="width: 60%;">
                            <label class="text-sm block mb-1">Tipo Documento</label>
                            <select class="text-sm border rounded px-2 py-1 w-full"
                                wire:model="nuevoCliente.tipo_documento_id">
                                <option value="">Seleccione</option>
                                @foreach (\App\Models\TipoDocumento::all() as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->nombre }}</option>
                                @endforeach
                            </select>
                            @error('nuevoCliente.tipo_documento_id')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="width: 40%;">
                            <label class="text-sm block mb-1">Numero de documento</label>
                            <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.identificacion"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            @error('nuevoCliente.identificacion')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-2" style="width: 100%;">
                        <label class="text-sm block mb-1">Nombre</label>
                        <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.nombre">
                        @error('nuevoCliente.nombre')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                    <div style="width: 100%;">
                        <label class="text-sm block mb-1">Razon Social</label>
                        <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.razon_social">
                        @error('nuevoCliente.razon_social')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-indigo-700 mb-2">Datos de contacto</h3>
                    <div class="mb-2" style="width: 100%;">
                        <label class="text-sm block mb-1">Correo electronico</label>
                        <input type="email" class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.email">
                        @error('nuevoCliente.email')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex gap-4">
                        <div style="width: 50%;">
                            <label class="text-sm">Telefono</label>
                            <input type="tel" pattern="[0-9]*" inputmode="numeric"
                                class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.telefono"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            @error('nuevoCliente.telefono')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="width: 50%;">
                            <label class="text-sm">Direccion</label>
                            <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.direccion">
                            @error('nuevoCliente.direccion')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-indigo-700 mb-2">Ubicacion</h3>
                    <div class="flex gap-4">
                        <div style="width: 50%;">
                            <label class="text-sm">Departamento</label>
                            <select class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.live="nuevoCliente.departamento_id">
                                <option value="">Seleccione...</option>
                                @foreach (\App\Models\Departamento::all() as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                                @endforeach
                            </select>
                            @error('nuevoCliente.departamento_id')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="width: 50%;">
                            <label class="text-sm">Ciudad</label>
                            <select class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.live="nuevoCliente.ciudad_id">
                                <option value="">Seleccione...</option>
                                @if (!empty($nuevoCliente['departamento_id']))
                                    @foreach (\App\Models\Ciudad::where('departamento_id', $nuevoCliente['departamento_id'])->get() as $ciu)
                                        <option value="{{ $ciu->id }}">{{ $ciu->nombre }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @error('nuevoCliente.ciudad_id')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-indigo-700 mb-2">Datos tributarios</h3>
                    <div class="flex gap-4">
                        <div style="width: 33.3%;">
                            <label class="text-sm">Tipo de persona</label>
                            <select class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.tipo_persona">
                                <option value="">Seleccione</option>
                                <option value="natural">Natural</option>
                                <option value="juridica">Juridica</option>
                            </select>
                            @error('nuevoCliente.tipo_persona')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="width: 33.3%;">
                            <label class="text-sm">Regimen tributario</label>
                            <select class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.regimen_tributario">
                                <option value="">Seleccione</option>
                                <option value="comun">Comun</option>
                                <option value="simplificado">Simplificado</option>
                                <option value="especial">Especial</option>
                                <option value="otro">Otro</option>
                            </select>
                            @error('nuevoCliente.regimen_tributario')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div style="width: 33.3%;">
                            <label class="text-sm">Responsable de IVA?</label>
                            <select class="border rounded px-2 py-1 w-full text-sm"
                                wire:model.defer="nuevoCliente.responsable_iva">
                                <option value="">Seleccione</option>
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                            @error('nuevoCliente.responsable_iva')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                
                <div class="flex justify-center gap-4 mt-6 sticky bottom-0 bg-white pt-3 pb-1 border-t">
                    <button x-data
                        x-on:click="
                        Swal.fire({
                            title: 'Crear cliente?',
                            text: 'Deseas guardar este nuevo cliente?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#38a169',
                            cancelButtonColor: '#e53e3e',
                            confirmButtonText: 'Si, guardar',
                            cancelButtonText: 'Cancelar',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $wire.guardarCliente();
                            }
                        });
                    "
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm">
                        Guardar
                    </button>

                    <button wire:click="cerrarModalCrearCliente"
                        class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm">
                        Cancelar
                    </button>
                </div>
                </div>
            </div>
        </div>
    @endif

    @if ($mostrarModalClientes)
        <div class="fixed inset-0 flex items-center justify-center" style="z-index:2147482400;background:rgba(15,23,42,.62);padding:16px;">
            <div class="bg-white rounded-xl shadow-2xl border overflow-hidden flex flex-col" style="width:min(560px, calc(100vw - 32px));max-height:min(680px, calc(100dvh - 32px));border-color:#bfdbfe;background:#f8fbff;">
                <div class="px-5 py-3 border-b shrink-0 text-center" style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;border-bottom:0;">
                    <h2 class="text-base font-bold" style="color:#fff;">Seleccionar Cliente</h2>
                </div>

                <div class="p-4 border-b bg-white shrink-0" style="border-color:#dbeafe;">
                    <input type="text" wire:model.live="buscarCliente"
                        placeholder="Buscar por nombre o cedula..."
                        class="w-full px-3 py-2 border rounded-lg text-sm" />
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-3" style="background:#f8fbff;">
                    <table class="w-full text-sm bg-white rounded-lg overflow-hidden shadow-sm border" style="border-color:#dbeafe;">
                        @forelse ($clientes as $cliente)
                            <tr wire:key="cliente-{{ $cliente->id }}"
                                wire:click="seleccionarCliente({{ $cliente->id_clip_pro }})"
                                class="hover:bg-indigo-50 cursor-pointer border-b">
                                <td class="px-3 py-2">
                                    <div class="font-semibold">{{ $cliente->nombre }}</div>
                                    <div class="text-xs text-gray-500">
                                        Cedula: {{ $cliente->identificacion ?? '-' }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-4 text-center text-gray-500">
                                    Cliente no existe
                                </td>
                            </tr>
                        @endforelse
                    </table>
                </div>

                <div class="px-4 py-3 border-t bg-white text-center shrink-0" style="border-color:#dbeafe;">
                    <button wire:click="$set('mostrarModalClientes', false)"
                        class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-8 py-2 rounded-full shadow-sm" style="min-width:140px;">
                        Cerrar
                    </button>
                </div>
                </div>
            </div>
        </div>
    @endif


    @if ($mostrarModal)
        <div class="fixed inset-0 flex items-center justify-center" style="z-index: 2147482800; padding:16px;">
            <div class="absolute inset-0 bg-black/60" style="background:rgba(15,23,42,.62);"></div>
            <div class="relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                style="z-index:2147482801;width:min(1120px, calc(100vw - 32px));height:min(720px, calc(100dvh - 32px));max-height:calc(100dvh - 32px);border-color:#bfdbfe;background:#f8fbff;" x-data="{
                    total: 0,
                    totalOriginal: 0,
                    recalcularTotal() {
                        setTimeout(() => {
                            let total = 0;
                            document.querySelectorAll('tr[data-carrito-item] span[data-total]').forEach(span => {
                                let valor = parseFloat(span.innerText.replace('$', '').replace(/\./g, '').replace(',', '.') || 0);
                                total += valor;
                            });
                            this.total = total;
                        }, 10);
                    }
                }"
                x-init="recalcularTotal()
                setTimeout(() => { totalOriginal = total }, 20);" @recalcular-total.window="recalcularTotal()">
                <div class="px-6 py-3 border-b flex items-center justify-between shrink-0" style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;border-bottom:0;">
                    <h2 class="text-base font-semibold" style="color:#fff;">Editar Detalles de Productos</h2>
                </div>
                <div class="p-4 flex-1 overflow-hidden" style="background:#f8fbff;min-height:0;height:100%;position:relative;">
                <div class="pos-edit-products-scroll rounded border bg-white" style="position:absolute;left:16px;right:16px;top:16px;bottom:126px;overflow:auto;min-height:0;padding-bottom:12px;">
                    <table class="pos-edit-products-table min-w-full text-xs bg-white border border-gray-300">
                        <colgroup>
                            <col style="width: 10%">
                            <col style="width: 25%">
                            <col style="width: 6%">
                            <col style="width: 7%">
                            <col style="width: 10%">
                            <col style="width: 10%">
                            <col style="width: 10%">
                            <col style="width: 7%">
                            <col style="width: 7%">
                            <col style="width: 8%">
                            <col style="width: 10%">
                        </colgroup>
                        <thead class="bg-gray-100 sticky top-0 z-10 border-b border-gray-300" style="position:sticky;top:0;z-index:20;background:#63a0d7;">
                            <tr class="text-gray-900 border-b font-bold text-sm">
                                <th class="px-2 p-2 border py-2 text-center">
                                    Codigo
                                </th>
                                <th class="px-2 p-2 border py-2 text-left">Nombre del Producto</th>
                                <th class="px-2 p-2 border py-2 text-center">IVA</th>
                                <th class="px-2 p-2 border py-2 text-center">% Util.</th>
                                <th class="px-2 p-2 border py-2 text-center">Cantidad</th>
                                <th class="px-2 p-2 border py-2 text-center">P. Costo</th>
                                <th class="px-2 p-2 border py-2 text-center">P. Real</th>
                                <th class="px-2 p-2 border py-2 text-center">P. Nuevo</th>
                                <th class="px-2 p-2 border py-2 text-center">% Dto.</th>
                                <th class="px-2 p-2 border py-2 text-center">% Nu. Util.</th>
                                <th class="px-2 p-2 border py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($carrito as $id => $item)
                                @php
                                    $costoBase = (float) ($item['costo'] ?? 0);
                                    $ivaVenta = (float) ($item['iva_venta'] ?? 0);
                                    $costoConIva =
                                        (float) ($item['costo_iva'] ??
                                            round($costoBase + ($costoBase * $ivaVenta) / 100, 2));
                                    $precioReal = (float) ($preciosBase[$id] ?? ($item['precio'] ?? 0));
                                    $utilidadBase =
                                        $precioReal > 0 ? (($precioReal - $costoConIva) / $precioReal) * 100 : 0;
                                @endphp
                                <tr data-carrito-item wire:key="carrito-{{ $id }}"
                                    :key="'carrito-' + {{ $id }}" x-data="{
                                        precioReal: {{ $precioReal }},
                                        descuento: {{ floatval($item['descuento'] ?? 0) }},
                                        nuevoPrecio: {{ floatval($item['nuevo_precio'] ?? ($item['precio'] ?? 0)) }},
                                    
                                        descuentoManual: false,
                                        costo: {{ floatval($item['costo'] ?? 0) }},
                                        costoConIvaBase: {{ $costoConIva }},
                                        iva: {{ floatval($item['iva_venta'] ?? 0) }},
                                        cantidad: {{ floatval($item['cantidad'] ?? 1) }},
                                    
                                        get costoMasIva() {
                                            return this.costoConIvaBase;
                                        },
                                        init() {
                                            // Si ya hay un descuento aplicado, calcularlo basado en precio actual vs precio real
                                            if (this.nuevoPrecio !== this.precioReal && this.precioReal > 0) {
                                                this.descuento = parseFloat((((this.nuevoPrecio / this.precioReal) - 1) * 100).toFixed(2));
                                            }
                                        },
                                    
                                        recalculaPorDescuento() {
                                            if (!this.descuentoManual) return;
                                            if (isNaN(parseFloat(this.descuento))) return;
                                    
                                            let nuevo = Math.round(this.precioReal * (1 + parseFloat(this.descuento) / 100));
                                    
                                            // Verifica si el nuevo precio es menor al costo + IVA
                                            if (nuevo < this.costoMasIva) {
                                                nuevo = Math.round(this.costoMasIva);
                                                this.descuento = this.precioReal ?
                                                    parseFloat((((nuevo / this.precioReal) - 1) * 100).toFixed(2)) :
                                                    0;
                                            }
                                    
                                            this.nuevoPrecio = nuevo;
                                        },
                                    
                                        recalculaPorPrecio() {
                                            this.nuevoPrecio = Math.round(this.nuevoPrecio); // <-- fuerza a entero siempre
                                            this.descuento = this.precioReal ?
                                                parseFloat((((this.nuevoPrecio / this.precioReal) - 1) * 100).toFixed(2)) :
                                                0;
                                        },
                                    
                                        utilidadNueva() {
                                            let costoConIva = this.costoMasIva;
                                            if (this.nuevoPrecio <= 0) return '0.00';
                                            return ((this.nuevoPrecio - costoConIva) / this.nuevoPrecio * 100).toFixed(2);
                                        },
                                    
                                        total() {
                                            return (this.nuevoPrecio * this.cantidad).toLocaleString('es-CO', { minimumFractionDigits: 2 });
                                        }
                                    }">
                                    <td class="px-2 p-2 border py-2 text-center font-mono">
                                        {{ $item['id_producto'] ?? '' }}</td>
                                    <td class="px-2 p-2 border py-2 text-left">{{ $item['nombre'] ?? '' }}</td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        {{ isset($item['iva_venta']) ? intval($item['iva_venta']) . '%' : '0%' }}
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        {{ number_format($utilidadBase, 2, ',', '.') . '%' }}
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center" data-cantidad>
                                        {{ $item['cantidad'] ?? 1 }}</td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        ${{ number_format($item['costo'] ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        ${{ number_format($preciosBase[$id] ?? ($item['precio'] ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        <input type="text" inputmode="numeric" x-ref="nuevoPrecio{{ $id }}"
                                            :value="nuevoPrecio.toLocaleString('es-CO')"
                                            @input="
                            nuevoPrecio = parseInt($event.target.value.replace(/\D/g, '')) || 0;
                            $event.target.value = nuevoPrecio.toLocaleString('es-CO');
                            recalculaPorPrecio();
                            $dispatch('recalcular-total');
                        "
                                            @blur="
                            if (nuevoPrecio < costoMasIva) {
                                nuevoPrecio = Math.round(costoMasIva);
                            }
                            recalculaPorPrecio();
                            $dispatch('recalcular-total');
                        "
                                            :class="nuevoPrecio < costoMasIva ?
                                                'border-red-600 bg-red-50 text-red-700' :
                                                'border-gray-300'"
                                            class="border rounded px-1 py-1 w-20 text-right transition-colors duration-150">

                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        <input type="number" step="0.01" x-ref="descuento{{ $id }}"
                                            x-model="descuento"
                                            @input="
                            descuentoManual = true;
                            descuento = descuento.toString().replace(/[^0-9\.\-]/g, '');
                            if (descuento === '-' || descuento === '') return;
                            recalculaPorDescuento();
                            $dispatch('recalcular-total');
                        "
                                            class="border rounded px-1 py-1 w-16 text-center">
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        <span x-text="utilidadNueva() + '%'"></span>
                                    </td>
                                    <td class="px-2 p-2 border py-2 text-center">
                                        <span data-total x-text="'$' + total()"></span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pos-edit-products-total text-right text-lg font-bold text-gray-900" style="position:absolute;left:auto;right:42px;bottom:86px;z-index:2;background:#f8fbff;box-sizing:border-box;white-space:nowrap;overflow:visible;width:auto;min-width:260px;max-width:calc(100% - 84px);">
                    TOTAL: $<span x-text="total.toLocaleString('es-CO')"></span>
                </div>
                <template x-if="total !== totalOriginal">
                    <div class="text-right" style="position:absolute;left:auto;right:42px;bottom:62px;z-index:2;background:#f8fbff;box-sizing:border-box;white-space:nowrap;overflow:visible;width:auto;min-width:260px;max-width:calc(100% - 84px);">
                        <span :class="total > totalOriginal ? 'text-green-600' : 'text-red-600'" class="font-semibold"
                            x-text="(total > totalOriginal ? 'Aumento: $' : 'Descuento: $') + Math.abs(total - totalOriginal).toLocaleString('es-CO')"></span>
                    </div>
                </template>
                
                <div class="flex justify-end gap-4 border-t bg-white px-4" style="position:absolute;left:16px;right:16px;bottom:0;z-index:3;min-height:58px;align-items:center;">
                    <button x-data
                        x-on:click="
                        const cambios = {};
                        document.querySelectorAll('tr[data-carrito-item]').forEach(tr => {
                            const key = tr.getAttribute('wire:key').replace('carrito-', '');
                            const nuevo = tr.querySelector('input[x-ref^=nuevoPrecio]')?.value;
                            const desc = tr.querySelector('input[x-ref^=descuento]')?.value;

                            if (key) {
                                cambios[key] = {
                                    nuevo_precio: parseInt((nuevo || '').replace(/\D/g, '')) || 0,
                                    descuento: parseFloat(desc) || 0
                                };
                            }
                        });
                        
                        $wire.aplicarCambiosModal(cambios);
                    "
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 text-sm rounded-full shadow transition font-bold"
                        title="Aplicar cambios">
                        Aplicar cambios
                    </button>

                    <button wire:click="$set('mostrarModal', false)"
                        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 text-sm rounded-full shadow transition font-bold"
                        title="Cancelar">
                        Cancelar
                    </button>
                </div>
                </div>
            </div>
        </div>
    @endif

    @if ($mostrarModalPrefacturas)
        <div class="pos-pro">
            <div class="pos-prefacturas-overlay fixed inset-0 flex items-center justify-center" style="z-index:2147482600; padding:16px;">
                
                <div class="absolute inset-0 bg-black/60" style="background:rgba(15,23,42,.62);"></div>

                
                <div class="pos-prefacturas-dialog relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                    style="z-index:2147482601;width:min(1120px, calc(100vw - 32px));height:min(720px, calc(100dvh - 32px));max-height:calc(100dvh - 32px);border-color:#bfdbfe;background:#f8fbff;">

                    
                    <div class="pos-prefacturas-header px-6 py-3 border-b flex items-center justify-between shrink-0" style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;border-bottom:0;">
                        <div class="flex items-center gap-6 flex-wrap">

                            <span class="text-lg font-bold" style="color:#fff;">
                                Gestion de {{ $tab === 'prefacturas' ? 'Prefacturas' : 'Facturas' }}
                            </span>

                            <div class="flex gap-2">
                                @php
                                    $configModalFacturas = \App\Models\ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())->first();
                                    $esTaller = (bool) ($configModalFacturas?->usa_taller);
                                    $esHotelModal = (bool) ($configModalFacturas?->usa_hotel);
                                @endphp
                                @if(! $mesaId && ! $esTaller && ! $esHotelModal)
                                <button wire:click="setTab('prefacturas')"
                                    class="px-3 py-1.5 rounded-full text-sm font-semibold shadow-sm {{ $tab === 'prefacturas' ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700 border border-indigo-200' }}">
                                    Prefacturas
                                </button>
                                @endif

                                <button wire:click="setTab('facturas')"
                                    class="px-3 py-1.5 rounded-full text-sm font-semibold shadow-sm {{ $tab === 'facturas' ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700 border border-indigo-200' }}">
                                    Facturas
                                </button>
                            </div>

                        </div>
                    </div>

                    
                    @if ($tab === 'facturas')
                        <div class="px-6 py-2 border-b text-sm flex items-end gap-3 flex-wrap" style="background:#f8fbff;">
                            <label class="text-xs text-gray-600 flex flex-col">
                                <span>Desde</span>
                                <input type="date" wire:model.live.debounce.300ms="fDesde"
                                    min="{{ $minFechaFacturas }}" max="{{ $maxFechaFacturas }}"
                                    class="border rounded-md px-2 h-[38px] leading-none" />
                            </label>

                            <label class="text-xs text-gray-600 flex flex-col">
                                <span>Hasta</span>
                                <input type="date" wire:model.live.debounce.300ms="fHasta"
                                    min="{{ $minFechaFacturas }}" max="{{ $maxFechaFacturas }}"
                                    class="border rounded-md px-2 h-[38px] leading-none" />
                            </label>

                            <button type="button" wire:click="actualizarRangoFacturas"
                                class="inline-flex items-center justify-center h-[38px] px-4 rounded-md bg-gray-800 text-white hover:bg-gray-900 leading-none">
                                Filtrar
                            </button>


                        </div>
                    @endif

                    
                    <div class="pos-prefacturas-body flex flex-1 overflow-hidden text-sm text-gray-800" style="background:#f8fbff;">

                        
                        <div class="pos-prefacturas-list w-1/2 border-r overflow-y-auto p-3 bg-white">
                            @if ($tab === 'prefacturas')

                                <table class="w-full table-fixed border-collapse">
                                    <thead class="bg-gray-100 sticky top-0 z-10 border-b border-gray-300" style="position:sticky;top:0;z-index:20;background:#63a0d7;">
                                        <tr class="text-left">
                                            <th class="p-2 border text-xs">#</th>
                                            <th class="p-2 border text-xs">Fecha</th>
                                            <th class="p-2 border text-xs">Hora</th>
                                            <th class="p-2 border text-xs">Cliente</th>
                                            <th class="p-2 border text-xs">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($prefacturas as $pf)
                                            @php
                                                $fecha = $pf->created_at->format('Y-m-d');
                                                $hora = $pf->created_at->format('H:i');
                                                $cliente = optional($pf->cliente)->nombre ?? 'Sin cliente';
                                                $total =
                                                    '$' . number_format($pf->productos->sum('subtotal'), 0, ',', '.');
                                            @endphp
                                            <tr wire:click="seleccionarPrefactura({{ $pf->id }})"
                                                class="hover:bg-indigo-50 cursor-pointer {{ $prefacturaSeleccionada && $pf->id === $prefacturaSeleccionada->id ? 'bg-indigo-100 font-semibold' : '' }}">
                                                <td class="p-2 border">{{ $pf->id }}</td>
                                                <td class="p-2 border">{{ $fecha }}</td>
                                                <td class="p-2 border">{{ $hora }}</td>
                                                <td class="p-2 border">{{ $cliente }}</td>
                                                <td class="p-2 border">{{ $total }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                
                                @php
                                    $agrupadas = collect($facturas)->groupBy(
                                        fn($f) => \Carbon\Carbon::parse($f->fecha)->format('Y-m-d'),
                                    );
                                @endphp

                                @forelse ($agrupadas as $dia => $items)
                                    <div class="font-bold text-indigo-700 mt-2 mb-1">{{ $dia }}</div>
                                    <table class="w-full table-fixed border-collapse mb-3">
                                        <thead class="bg-gray-100 sticky top-0 z-10 border-b border-gray-300" style="position:sticky;top:0;z-index:20;background:#63a0d7;">
                                            <tr class="text-left">
                                                <th class="p-2 border text-xs w-14">#</th>
                                                <th class="p-2 border text-xs">Hora</th>
                                                <th class="p-2 border text-xs">Cliente</th>
                                                <th class="p-2 border text-xs w-24 text-right">Total</th>
                                                <th class="p-2 border text-xs w-24 text-center">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($items as $f)
                                                @php
                                                    $hora = \Carbon\Carbon::parse($f->fecha)->format('H:i');
                                                    $cliente = optional($f->cliente)->nombre ?? 'Sin cliente';
                                                    $total = '$' . number_format($f->total, 0, ',', '.');
                                                    $cls =
                                                        $facturaSeleccionada && $f->id === $facturaSeleccionada->id
                                                            ? 'bg-indigo-100 font-semibold'
                                                            : '';
                                                @endphp
                                                <tr wire:click="seleccionarFactura({{ $f->id }})"
                                                    class="hover:bg-indigo-50 cursor-pointer {{ $cls }}">
                                                    <td class="p-2 border">{{ $f->numero_visual }}</td>
                                                    <td class="p-2 border">{{ $hora }}</td>
                                                    <td class="p-2 border">{{ $cliente }}</td>
                                                    <td class="p-2 border text-right">{{ $total }}</td>
                                                    <td class="p-2 border text-center">
                                                        @if ($f->devuelta_total)
                                                            <span class="text-red-600 font-bold">DEVUELTA</span>
                                                        @else
                                                            <span class="text-green-700">OK</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @empty
                                    <div class="text-sm text-gray-500">Sin facturas en el rango seleccionado.</div>
                                @endforelse
                            @endif
                        </div>

                        
                        <div class="pos-prefacturas-detail w-1/2 flex flex-col h-full"
                            wire:key="{{ $tab === 'prefacturas' ? 'panel-prefactura-' . ($prefacturaSeleccionada->id ?? 'none') : 'panel-factura' }}">

                            
                            @if ($tab === 'prefacturas')

                                @if ($prefacturaSeleccionada)
                                    <h3 class="text-base font-semibold text-gray-800 mb-2">Detalles Prefactura</h3>

                                    <div class="flex-1 overflow-y-auto border rounded-lg text-sm">
                                        <table class="w-full text-xs">
                                            <thead
                                                class="sticky top-0 bg-gray-100 text-gray-900 font-semibold z-10 border-b border-gray-300">
                                                <tr>
                                                    <th class="p-2 border">Codigo</th>
                                                    <th class="p-2 border">Descripcion</th>
                                                    <th class="p-2 border text-center">Cant</th>
                                                    <th class="p-2 border text-right">Precio</th>
                                                    <th class="p-2 border text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($detalleSeleccionado as $item)
                                                    <tr class="even:bg-gray-50 hover:bg-indigo-50 transition">
                                                        <td class="p-2 border text-center">{{ $item['producto_id'] }}
                                                        </td>
                                                        <td class="p-2 border">{{ $item['descripcion_larga'] }}</td>
                                                        <td class="p-2 border text-center">{{ $item['cantidad'] }}
                                                        </td>
                                                        <td class="p-2 border text-right">
                                                            ${{ number_format($item['precio_unitario'], 0, ',', '.') }}
                                                        </td>
                                                        <td class="p-2 border text-right">
                                                            ${{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-2 px-1">
                                        <div
                                            class="flex justify-between text-sm text-gray-800 font-semibold border-t pt-2">
                                            <span>Total items: {{ count($detalleSeleccionado) }}</span>
                                            <span>Total:
                                                ${{ number_format(collect($detalleSeleccionado)->sum('subtotal'), 0, ',', '.') }}</span>
                                        </div>

                                        <div class="mt-2 text-sm">
                                            <label
                                                class="font-semibold text-gray-700 block mb-1">Observaciones:</label>
                                            <div class="bg-gray-100 p-2 rounded text-gray-800">
                                                {{ $observacionesPrefactura }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div
                                        class="text-sm text-gray-500 flex-1 flex items-center justify-center text-center">
                                        Selecciona una prefactura para ver los detalles.
                                    </div>
                                @endif

                                
                            @else
                                @if ($facturaSeleccionada)
                                    <h3 class="text-base font-semibold text-gray-800 mb-2">
                                        Detalles de Factura
                                    </h3>

                                    <div class="flex-1 overflow-y-auto border rounded-lg text-sm">
                                        <table class="w-full text-xs">
                                            <thead
                                                class="sticky top-0 bg-gray-100 text-gray-900 font-semibold z-10 border-b border-gray-300">
                                                <tr>
                                                    <th class="p-2 border">Codigo</th>
                                                    <th class="p-2 border">Descripcion</th>
                                                    <th class="p-2 border text-center">Cant</th>
                                                    <th class="p-2 border text-center">Dev.</th>
                                                    <th class="p-2 border text-center">Pend.</th>
                                                    <th class="p-2 border text-right">Precio</th>
                                                    <th class="p-2 border text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($detalleFacturaSeleccionada as $d)
                                                    <tr class="even:bg-gray-50">
                                                        <td class="p-2 border text-center">{{ $d['producto_id'] == 0 ? '—' : $d['producto_id'] }}
                                                        </td>
                                                        <td class="p-2 border">{{ $d['descripcion_larga'] }}</td>
                                                        <td class="p-2 border text-center">{{ $d['cantidad'] }}</td>
                                                        <td class="p-2 border text-center text-blue-700 font-semibold">
                                                            {{ $d['devuelto_cantidad'] }}</td>
                                                        <td
                                                            class="p-2 border text-center text-amber-700 font-semibold">
                                                            {{ $d['pendiente'] }}</td>
                                                        <td class="p-2 border text-right">
                                                            ${{ number_format($d['precio'], 0, ',', '.') }}</td>
                                                        <td class="p-2 border text-right">
                                                            ${{ number_format($d['subtotal'], 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-2 px-1">
                                        <div
                                            class="flex justify-between text-sm text-gray-800 font-semibold border-t pt-2">
                                            <span>Total items: {{ count($detalleFacturaSeleccionada) }}</span>
                                            <span>Total:
                                                ${{ number_format($facturaSeleccionada->total, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 px-1 text-sm">
                                        <label class="font-semibold text-gray-700 block mb-1">Observaciones:</label>
                                        <div
                                            class="bg-gray-100 p-2 rounded text-gray-800 whitespace-pre-line min-h-[38px]">
                                            {{ $facturaSeleccionada->observaciones ?: '-' }}
                                        </div>
                                    </div>
                                @else
                                    <div
                                        class="text-sm text-gray-500 flex-1 flex items-center justify-center text-center">
                                        Selecciona una factura para ver los detalles.
                                    </div>
                                @endif

                                
                                <div class="pos-prefacturas-footer p-3 flex justify-end gap-3 border-t mt-2 bg-white">
                                    @if ($facturaSeleccionada)
                                        <button
                                            onclick="window.open('{{ route('factura.imprimir', $facturaSeleccionada->id) }}','_blank','width=400,height=600')"
                                            class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                                            Imprimir
                                        </button>

                                        @if (auth()->user()->hasAnyRole(['cajero', 'admin_empresa', 'recepcion']))
                                            <button x-data
                                                x-on:click="
                    (async () => {
                      if (!window.Swal) { alert('SweetAlert no esta cargado'); return; }

                      // 1) Elegir tipo (completa/parcial)
                      const res = await Swal.fire({
                        title: 'Devolucion',
                        input: 'select',
                        inputOptions: { completa: 'Completa', parcial: 'Parcial' },
                        inputValue: 'completa',
                        showCancelButton: true,
                        confirmButtonText: 'Continuar',
                        cancelButtonText: 'Cancelar',
                      });
                      if (!res.isConfirmed) return;

                      // 2) Pedir al backend que prepare el carrito de devolucion y muestre el modal
                      await $wire.prepararDevolucion(res.value);
                    })()
                  "
                                                class="h-9 px-4 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-full">
                                                Devolucion
                                            </button>
                                        @endif
                                    @endif

                                    
                                    <button wire:click="$set('mostrarModalPrefacturas', false)"
                                        class="h-9 px-4 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-full shadow-sm">
                                        Cerrar
                                    </button>
                                </div>
                            @endif

                        </div>
                    </div>

                    
                    @if ($tab === 'prefacturas')
                        <div class="pos-prefacturas-footer p-4 border-t flex justify-end gap-3 bg-white">
                            @if ($prefacturaSeleccionada)
                                <button
                                    onclick="window.open('{{ route('prefactura.imprimir', $prefacturaSeleccionada->id) }}','_blank','width=400,height=600')"
                                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                                    Imprimir
                                </button>

                                <button x-data
                                    x-on:click="
                                    Swal.fire({
                                      title: 'Borrar prefactura?',
                                      text: 'Esta accion eliminara la prefactura seleccionada. Estas seguro?',
                                      icon: 'warning',
                                      showCancelButton: true,
                                      confirmButtonColor: '#d33',
                                      cancelButtonColor: '#3085d6',
                                      confirmButtonText: 'Si, borrar',
                                      cancelButtonText: 'Cancelar',
                                      reverseButtons: true
                                    }).then((result)=>{ if(result.isConfirmed){ $wire.borrarPrefacturaConfirmada().then(()=>{ Swal.fire({title:'Prefactura eliminada',icon:'success',timer:1500,showConfirmButton:false}); }); } });"
                                    class="h-9 px-4 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-full">
                                    Borrar
                                </button>

                                @php $hayProductosEnCarrito = count($carrito) > 0; @endphp
                                <button x-data="{ hayProductos: {{ $hayProductosEnCarrito ? 'true' : 'false' }} }"
                                    x-on:click="
                                    if (hayProductos) {
                                      Swal.fire({icon:'warning',title:'Carrito con productos',text:'Limpie el carrito antes de cargar otra prefactura.',confirmButtonText:'Entendido'});
                                    } else {
                                      $wire.cargarPrefacturaAlCarrito({{ $prefacturaSeleccionada->id }});
                                    }"
                                    wire:key="prefactura-btn-{{ $prefacturaSeleccionada->id }}"
                                    wire:loading.attr="disabled" wire:target="cargarPrefacturaAlCarrito"
                                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                                    Cargar
                                </button>
                            @endif

                            <button wire:click="$set('mostrarModalPrefacturas', false)"
                                class="h-9 px-4 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-full shadow-sm">
                                Cerrar
                            </button>
                        </div>
                    @endif

                </div>
            </div>

    @endif


    @if ($mostrarModalDevolucion)
        <div x-data>
            <template x-teleport="body">
                <div class="fixed inset-0 flex items-center justify-center"
                    style="z-index:2147483647 !important; left:0; top:0; right:0; bottom:0; background:rgba(15,23,42,.62); padding:16px; overflow:auto; pointer-events:auto;">
                    <div class="pos-devolucion-dialog relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                        style="z-index:2147483647 !important; pointer-events:auto;width:min(1120px, calc(100vw - 32px));height:min(720px, calc(100dvh - 32px));max-height:calc(100dvh - 32px);border-color:#bfdbfe;background:#f8fbff;">
                        <div class="px-6 py-3 border-b flex items-center justify-between shrink-0"
                            style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;border-bottom:0;">
                            <div class="flex items-center gap-4 flex-wrap">
                                <span class="text-lg font-bold" style="color:#fff;">
                                    Devolucion
                                    @if ($tipoDevolucion === 'completa')
                                        (Completa)
                                    @else
                                        (Parcial)
                                    @endif
                                </span>
                            </div>

                            <button class="text-white/90 hover:text-white text-xl leading-none font-semibold"
                                wire:click="$set('mostrarModalDevolucion', false)">x</button>
                        </div>

                        <div class="px-6 py-2 border-b text-sm flex items-end gap-3 flex-wrap"
                            style="background:#f8fbff;">
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="seleccionarTodosDevolucion"
                                    class="inline-flex items-center justify-center h-[38px] px-4 rounded-md bg-gray-800 text-white hover:bg-gray-900 leading-none">
                                    Seleccionar todos
                                </button>
                                <button wire:click="limpiarSeleccionDevolucion"
                                    class="inline-flex items-center justify-center h-[38px] px-4 rounded-md bg-gray-200 text-gray-800 hover:bg-gray-300 leading-none">Limpiar</button>
                            </div>

                            <div class="ml-auto text-sm text-gray-600">
                                Cliente: <span
                                    class="font-semibold">{{ $facturaSeleccionada?->cliente?->nombre ?? 'N/A' }}</span>
                            </div>
                        </div>

                        <div class="flex-1 overflow-hidden p-0 bg-[#f8fbff]">
                            <div class="h-full overflow-y-auto overflow-x-auto p-3">
                                <table class="min-w-[760px] w-full text-sm bg-white rounded-xl overflow-hidden shadow-sm border"
                                    style="border-color:#bfdbfe;">
                                    <thead class="bg-gray-100 sticky top-0 z-10 border-b border-gray-300"
                                        style="position:sticky;top:0;z-index:20;background:#63a0d7;">
                                        <tr>
                                            <th class="px-3 py-2 text-left">#</th>
                                            <th class="px-3 py-2 text-left">Codigo</th>
                                            <th class="px-3 py-2 text-left">Descripcion</th>
                                            <th class="px-3 py-2 text-right">Pendiente</th>
                                            <th class="px-3 py-2 text-right">Precio</th>
                                            <th class="px-3 py-2 text-right">Cantidad</th>
                                            <th class="px-3 py-2 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($carritoDevolucion as $detId => $row)
                                            <tr class="border-t even:bg-gray-50 hover:bg-indigo-50 transition">
                                                <td class="px-3 py-2">
                                                    <input type="checkbox"
                                                        wire:click="toggleSeleccionDevolucion({{ $detId }})"
                                                        {{ $row['seleccion'] ? 'checked' : '' }}>
                                                </td>
                                                <td class="px-3 py-2">{{ $row['producto_id'] == 0 ? '—' : $row['producto_id'] }}</td>
                                                <td class="px-3 py-2">{{ $row['descripcion'] }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format($row['pendiente'], 2) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format($row['precio'], 2) }}</td>
                                                <td class="px-3 py-2 text-right">
                                                    <input type="number" min="0" max="{{ $row['pendiente'] }}"
                                                        step="0.01" class="w-24 border rounded px-2 py-1 text-right"
                                                        value="{{ $row['cantidad'] }}"
                                                        wire:change="setCantidadDevolucion({{ $detId }}, $event.target.value)">
                                                </td>
                                                <td class="px-3 py-2 text-right font-semibold">
                                                    {{ number_format($row['subtotal'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay productos pendientes para devolver.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="px-4 py-3 flex items-center justify-end gap-4 border-t bg-white">
                                <div class="text-sm text-gray-600">Total devolucion:</div>
                                <div class="text-xl font-extrabold">${{ number_format($totalDevolucion, 2) }}</div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-end sm:gap-3 sm:px-5 bg-white">
                            <button class="h-9 px-4 rounded-full bg-gray-200 hover:bg-gray-300"
                                wire:click="$set('mostrarModalDevolucion', false)">Cancelar</button>
                            <button class="h-9 px-4 rounded-full bg-red-600 hover:bg-red-700 text-white"
                                wire:click="confirmarDevolucion" @disabled(count(array_filter($carritoDevolucion, fn($r) => $r['seleccion'] && $r['cantidad'] > 0)) === 0)>
                                Devolver
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif


    @if ($mostrarModalRenombrar)
        <x-modal wire:model="mostrarModalRenombrar">
            <h2 class="text-lg font-bold mb-4">Cambiar nombre del producto</h2>

            <input type="text" wire:model.defer="nuevoNombreTemporal"
                class="w-full border border-gray-300 p-2 rounded" />

            <div class="flex justify-end gap-2 mt-4">
                <button wire:click="cerrarModalRenombrar"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 text-sm rounded-full shadow transition font-bold">Cancelar</button>
                <button wire:click="guardarNuevoNombre"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 text-sm rounded-full shadow transition font-bold">Guardar</button>
            </div>
        </x-modal>
    @endif


    @if ($mostrarModalAbrirCaja)
    <div wire:key="modal-abrir-caja"
        class="fixed inset-0 flex items-center justify-center" style="z-index:2147483000;background:rgba(15,23,42,.62);padding:16px;">

        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full"
            style="max-width:360px; text-align:center; font-size:14px">

            <h2 class="text-xl font-bold mb-4">Abrir Caja</h2>

            <div style="width:100%;max-width:280px;margin:0 auto;text-align:left">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">
                    Monto de apertura
                </label>

                <input type="number"
                    wire:model.defer="montoApertura"
                    class="w-full border rounded-lg px-3 h-10"
                    min="0"
                    step="1"
                    placeholder="0">
            </div>

            <div class="mt-5 flex justify-center gap-6 flex-wrap">
                <button type="button" wire:click="$set('mostrarModalAbrirCaja', false)"
                    class="h-11 min-w-[120px] px-6 rounded-full bg-red-500 hover:bg-red-600 text-white text-base font-semibold shadow-sm">
                    Cancelar
                </button>

                <button type="button" wire:click="confirmarAbrirCaja"
                    class="h-11 min-w-[120px] px-6 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-base font-semibold shadow-sm">
                    Abrir caja
                </button>
            </div>
        </div>
    </div>
@endif


    @if ($mostrarModalMovimientoCaja)
        <div wire:key="modal-movimiento-caja"
            class="pos-cierre-caja-overlay fixed inset-0 bg-black/50 flex items-center justify-center z-[200020]">
            <div class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 p-6 w-full"
                style="max-width:520px; max-height:90vh; overflow-y:auto" x-data="{
                    montoTexto: '',
                    init() {
                        this.montoTexto = this.formato($wire.movimientoCaja.monto || 0);
                    },
                    formato(valor) {
                        const limpio = String(valor || '').replace(/\D/g, '');
                        return limpio ? Number(limpio).toLocaleString('es-CO') : '';
                    },
                    actualizarMonto(valor) {
                        const limpio = String(valor || '').replace(/\D/g, '');
                        this.montoTexto = this.formato(limpio);
                        $wire.set('movimientoCaja.monto', limpio ? Number(limpio) : 0);
                    }
                }">
                <h2 class="text-xl font-bold mb-4">Movimiento de caja</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold block mb-1">Tipo</label>
                        <select wire:model.live="movimientoCaja.tipo" class="w-full border rounded-lg px-3 h-10">
                            <option value="salida">Salida / gasto</option>
                            <option value="entrada">Entrada</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1">Metodo</label>
                        <select wire:model.defer="movimientoCaja.metodo_pago" class="w-full border rounded-lg px-3 h-10">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Nequi">Nequi</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1">Categoria</label>
                        <select wire:model.defer="movimientoCaja.categoria" class="w-full border rounded-lg px-3 h-10">
                            <option value="Base adicional">Base adicional</option>
                            <option value="Retiro de efectivo">Retiro de efectivo</option>
                            <option value="Transporte">Transporte</option>
                            <option value="Empleados">Empleados</option>
                            <option value="Papeleria">Papeleria</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1">Monto</label>
                        <input type="text" inputmode="numeric" x-model="montoTexto"
                            @input="actualizarMonto($event.target.value)"
                            class="w-full border rounded px-3 h-9 text-right" placeholder="0">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="text-xs font-semibold block mb-1">Descripcion</label>
                    <input type="text" wire:model.defer="movimientoCaja.descripcion"
                        class="w-full border rounded-lg px-3 h-10"
                        placeholder="Ej: compra de bolsas, base adicional, retiro">
                </div>

                <div class="mt-3">
                    <label class="text-xs font-semibold block mb-1">Observacion</label>
                    <textarea wire:model.defer="movimientoCaja.observacion" class="w-full border rounded px-3 py-2" rows="3"
                        placeholder="Detalle opcional del movimiento"></textarea>
                </div>

                <div class="mt-5 flex justify-end gap-2 sticky bottom-0 bg-white pt-3">
                    <button type="button" class="text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm"
                        style="background:#ef4444 !important;color:#fff !important;"
                        wire:click="$set('mostrarModalMovimientoCaja', false)">Cancelar</button>
                    <button type="button"
                        class="bg-yellow-500 hover:bg-yellow-600 text-gray-950 font-bold px-4 py-2 rounded border border-yellow-700 shadow-sm"
                        wire:click="registrarMovimientoCaja">Guardar</button>
                </div>
            </div>
        </div>
    @endif


    @if ($mostrarModalCerrarCaja)
        <div wire:key="modal-cerrar-caja"
            class="fixed inset-0 flex items-center justify-center" style="z-index:2147483000;background:rgba(15,23,42,.62);padding:16px;">
            <div class="bg-white rounded-xl shadow-2xl border w-full overflow-hidden"
                style="max-width:560px;max-height:calc(100dvh - 32px);text-align:center;font-size:13px;display:flex;flex-direction:column;position:relative;z-index:2147483001;border-color:#bfdbfe;background:#f8fbff;"
                x-data="{
                    // Total contado del dia (efectivo + transferencia)
                    efectivoNeto: {{ (int) ($resumenCaja['efectivo'] ?? 0) }},
                    efectivoEsperado: {{ (int) ($resumenCaja['efectivo_esperado'] ?? ($resumenCaja['efectivo'] ?? 0)) }},
                    salidasEfectivo: {{ (int) ($resumenCaja['salidas_efectivo'] ?? 0) }},
                    devolucionesConPago: {{ (int) ($resumenCaja['devoluciones_con_pago'] ?? 0) }},
                    // enlazado al input 'Monto de cierre'
                    monto: @entangle('montoCierre').live,
                    // diferencia para el cuadre
                    get dif() { return Number(this.monto || 0) - Number(this.efectivoEsperado || 0) },
                    get abs() { return Math.abs(this.dif) },
                    fmt(n) { return '$' + Number(n || 0).toLocaleString('es-CO') },
                    montoTexto: '',
                    init() { this.montoTexto = Number(this.monto || 0).toLocaleString('es-CO') },
                    setMonto(value) {
                        const limpio = String(value || '').replace(/\D/g, '');
                        this.monto = limpio === '' ? 0 : Number(limpio);
                        this.montoTexto = limpio === '' ? '' : Number(limpio).toLocaleString('es-CO');
                    },
                    get estado() {
                        if (this.efectivoNeto < 0 && Number(this.monto || 0) === 0) {
                            const causa = Number(this.salidasEfectivo || 0) > Number(this.devolucionesConPago || 0) ? 'Caja negativa por salidas de efectivo:' : 'Caja negativa por devoluciones:';
                            return { txt: causa, cls: 'text-orange-600', val: Math.abs(this.efectivoNeto) };
                        }
                        if (this.dif > 0) return { txt: 'Sobro', cls: 'text-green-600' };
                        if (this.dif < 0) return { txt: 'Falto', cls: 'text-red-600' };
                        return { txt: 'Cuadre perfecto:', cls: 'text-gray-700' };
                    },
                    cancelar() { $wire.set('mostrarModalCerrarCaja', false) },
                    cerrar() { $wire.confirmarCerrarCaja() }
                }">

                <div class="px-5 py-3 shrink-0 flex items-center justify-center" style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;">
                    <h2 class="text-lg font-bold" style="color:#fff;">Cerrar Caja</h2>
                </div>

                <div class="p-4 flex-1 overflow-hidden flex flex-col" style="background:#f8fbff;min-height:0;">

                @php
                    $usaHotelCierre = (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())->value('usa_hotel');
                @endphp
                <div class="pos-cierre-resumen mb-3 bg-white rounded-lg p-3 border text-xs overflow-y-auto shadow-sm"
                    style="text-align:left;max-height:34vh;border-color:#dbeafe;min-height:0;">
                    <div class="font-semibold mb-2">Resumen</div>

                    <div class="text-[11px] text-gray-500 uppercase tracking-wide">VENTAS POR TIPO</div>
                    <div class="flex justify-between"><span>🔩 Productos</span><span
                            class="font-semibold">${{ number_format($resumenCaja['ventas_productos'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>🔧 Servicios</span><span
                            class="font-semibold">${{ number_format($resumenCaja['ventas_servicios'], 0, ',', '.') }}</span>
                    </div>

                    @if($usaHotelCierre && (($resumenCaja['abonos_hotel_efectivo'] ?? 0) + ($resumenCaja['abonos_hotel_transferencia'] ?? 0)) > 0)
                    <div class="mt-1 text-[11px] text-gray-500 uppercase tracking-wide">ABONOS DE RESERVAS (ya en caja)</div>
                    @if(($resumenCaja['abonos_hotel_efectivo'] ?? 0) > 0)
                    <div class="flex justify-between"><span>💰 Efectivo</span><span
                            class="font-semibold text-amber-700">${{ number_format($resumenCaja['abonos_hotel_efectivo'], 0, ',', '.') }}</span>
                    </div>
                    @endif
                    @if(($resumenCaja['abonos_hotel_transferencia'] ?? 0) > 0)
                    <div class="flex justify-between"><span>💰 Transferencia</span><span
                            class="font-semibold text-amber-700">${{ number_format($resumenCaja['abonos_hotel_transferencia'], 0, ',', '.') }}</span>
                    </div>
                    @endif
                    @endif

                    <div class="my-1 border-t"></div>

                    <div class="flex justify-between"><span>Efectivo esperado en caja</span><span
                            class="font-semibold">${{ number_format($resumenCaja['efectivo_esperado'] ?? ($resumenCaja['efectivo'] ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Transferencia del dia</span><span
                            class="font-semibold text-blue-700">${{ number_format($resumenCaja['transferencia'], 0, ',', '.') }}</span>
                    </div>

                    <div class="my-1 border-t"></div>

                    <div class="flex justify-between"><span>Total ventas</span><span
                            class="font-semibold">${{ number_format($resumenCaja['total_ventas'], 0, ',', '.') }}</span>
                    </div>

                    @unless($usaHotelCierre)

                    <div class="mt-2 text-[11px] text-gray-500 uppercase tracking-wide">VENTAS</div>
                    <div class="flex justify-between"><span>Contado - Efectivo</span><span
                            class="font-semibold">${{ number_format($resumenCaja['ventas_contado_efectivo'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Contado - Transferencia</span><span
                            class="font-semibold">${{ number_format($resumenCaja['ventas_contado_transferencia'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Credito</span><span
                            class="font-semibold">${{ number_format($resumenCaja['ventas_credito'], 0, ',', '.') }}</span>
                    </div>

                    <div class="mt-1 text-[11px] text-gray-500 uppercase tracking-wide">CARTERA</div>
                    <div class="flex justify-between"><span>Efectivo</span><span
                            class="font-semibold">${{ number_format($resumenCaja['cartera_efectivo'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Transferencia</span><span
                            class="font-semibold">${{ number_format($resumenCaja['cartera_transferencia'], 0, ',', '.') }}</span>
                    </div>

                    @if(($resumenCaja['dom_cobrado_total'] ?? 0) > 0)
                    <div class="mt-1 text-[11px] text-orange-600 uppercase tracking-wide font-bold">🛵 DOMICILIOS (a pagar al domiciliario)</div>
                    <div class="flex justify-between"><span>Cobrado en efectivo</span><span
                            class="font-semibold text-orange-600">${{ number_format($resumenCaja['dom_cobrado_efectivo'] ?? 0, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between"><span>Cobrado en transferencia</span><span
                            class="font-semibold text-orange-600">${{ number_format($resumenCaja['dom_cobrado_transferencia'] ?? 0, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between border-t border-orange-200 mt-1 pt-1"><span class="font-semibold">Total a pagar domiciliario</span><span
                            class="font-semibold text-orange-700">${{ number_format($resumenCaja['dom_cobrado_total'] ?? 0, 0, ',', '.') }}</span></div>
                    @endif

                    <div class="mt-1 text-[11px] text-gray-500 uppercase tracking-wide">MOVIMIENTOS DE CAJA</div>
                    <div class="flex justify-between"><span>Entradas efectivo</span><span
                            class="font-semibold text-green-700">${{ number_format($resumenCaja['entradas_efectivo'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Salidas efectivo</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['salidas_efectivo'] ?? 0, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between"><span>Entradas transf.</span><span
                            class="font-semibold text-blue-700">${{ number_format($resumenCaja['entradas_transferencia'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Salidas transf.</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['salidas_transferencia'] ?? 0, 0, ',', '.') }}</span></div>

                    <div class="my-1 border-t"></div>


                    <div class="flex justify-between"><span>Base / apertura</span><span
                            class="font-semibold">${{ number_format($resumenCaja['monto_apertura'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Efectivo recibido</span><span
                            class="font-semibold text-green-700">${{ number_format(($resumenCaja['ventas_contado_efectivo'] ?? 0) + ($resumenCaja['cartera_efectivo'] ?? 0) + ($resumenCaja['entradas_efectivo'] ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Devoluciones efectivo</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['devoluciones_con_pago'] ?? 0, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between"><span>Salidas efectivo</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['salidas_efectivo'] ?? 0, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between border-t mt-1 pt-1"><span>Efectivo neto del dia</span><span
                            class="font-semibold {{ ($resumenCaja['efectivo'] ?? 0) < 0 ? 'text-orange-600' : 'text-green-700' }}">${{ number_format($resumenCaja['efectivo'], 0, ',', '.') }}</span>
                    </div>

                    <div class="my-1 border-t"></div>

                    @if (($resumenCaja['ventas_passthrough'] ?? 0) > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>No incluye reembolsos a
                            terceros (item manual)</span><span>${{ number_format($resumenCaja['ventas_passthrough'], 0, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between"><span>Total contado</span><span
                            class="font-semibold">${{ number_format($resumenCaja['total_contado'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Devoluciones (con pago)</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['devoluciones_con_pago'] ?? 0, 0, ',', '.') }}</span></div>
                    @endunless
                </div>


                <div class="shrink-0" style="width:100%;max-width:320px;margin:0 auto;text-align:left">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Monto de
                        cierre</label>
                    <input type="text" inputmode="numeric" class="w-full border rounded-lg px-3 h-10"
                        x-model="montoTexto" x-on:input="setMonto($event.target.value)" placeholder="0">
                </div>

                <div class="mt-3 text-base font-bold shrink-0 rounded-lg py-2" style="background:#fff;">
                    <span :class="estado.cls"
                        x-text="estado.val !== undefined ? (estado.txt + ' ' + fmt(estado.val)) : (dif === 0 ? (estado.txt + ' $0') : (estado.txt + ' ' + fmt(abs)))"></span>
                    <div class="text-xs text-gray-500 mt-1">Contra <b>efectivo esperado en caja</b>. Transferencia
                        queda informativa.</div>
                </div>

                <div class="mt-4 shrink-0 flex justify-center gap-5 flex-wrap border-t bg-white px-4 py-4 -mx-4 -mb-4">
                    <button type="button" wire:click="$set('mostrarModalCerrarCaja', false)"
                        class="h-11 min-w-[120px] px-6 rounded-full bg-red-500 hover:bg-red-600 text-white text-base font-semibold shadow-sm">
                        Cancelar
                    </button>

                    <button type="button" wire:click="confirmarCerrarCaja"
                        class="h-11 min-w-[130px] px-6 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-base font-semibold shadow-sm">
                        Cerrar caja
                    </button>
                </div>
                </div>
            </div>
        </div>
    @endif


</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function openPrintPopup(url) {
        const popup = window.open(url, '_blank', 'width=420,height=700');
        if (popup) popup.focus();
        else window.location.href = url;
    }

    window.uiAbrirFacturar = function () {
        Livewire.dispatch('abrir-facturar');
    };

    window.posMesaEnviarCocinaModal = function (clienteNombreArg, clienteDireccionArg, clienteTelefonoArg, ordenDomNombreArg, ordenDomObsArg, ordenDomCostoDomArg, ordenDomCostoDesechArg, ordenEstadoArg, ordenTipoArg, usaDomiciliosArg, esMeseroArg, esMesaDomicilioArg) {
        if (!window.Swal) { Livewire.dispatch('mesa-enviar-cocina'); return; }

        // Mesa normal (no domicilio): enviar directo sin modal
        if (!esMesaDomicilioArg) {
            Livewire.dispatch('mesa-enviar-cocina-confirmado', { data: {
                tipo_pedido: 'mesa', costo_domicilio: 0, costo_empaque: 0,
                dom_nombre: null, dom_telefono: null, dom_direccion: null, dom_observaciones: null,
            }});
            return;
        }

        // Si ya está en cocina como domicilio, re-enviar directo con los datos guardados
        if ((ordenEstadoArg || '') === 'en_preparacion') {
            Livewire.dispatch('mesa-enviar-cocina-confirmado', { data: {
                tipo_pedido: 'domicilio',
                costo_domicilio: parseFloat(ordenDomCostoDomArg) || 0,
                costo_empaque: parseFloat(ordenDomCostoDesechArg) || 0,
                dom_nombre: (ordenDomNombreArg || '').trim() || null,
                dom_telefono: (clienteTelefonoArg || '').trim() || null,
                dom_direccion: (clienteDireccionArg || '').trim() || null,
                dom_observaciones: (ordenDomObsArg || '').trim() || null,
            }});
            return;
        }

        const clienteNombre       = (clienteNombreArg || '').trim();
        const clienteDireccion    = (clienteDireccionArg || '').trim();
        const clienteTelefono     = (clienteTelefonoArg || '').trim();
        const ordenDomNombre      = (ordenDomNombreArg || '').trim();
        const ordenDomObs         = (ordenDomObsArg || '').trim();
        const ordenDomCostoDom    = Number(ordenDomCostoDomArg) || 0;
        const ordenDomCostoDesech = Number(ordenDomCostoDesechArg) || 0;
        const esConsumidorFinal = !clienteNombre || clienteNombre.toUpperCase().includes('CONSUMIDOR FINAL') || clienteNombre.trim().toUpperCase() === 'CF';

        // Nombre a mostrar en el encabezado del bloque domicilio
        const nombreMostrar = ordenDomNombre || clienteNombre;

        const inputStyle = 'width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:12px;';

        // Bloque de datos (CF: campos vacíos; cliente real: pre-relleno con datos de la orden o del cliente)
        const clienteInfoHtml = esConsumidorFinal
            ? `<div id="pc_datos_cliente" style="display:none;margin-bottom:8px;">
                <div style="font-size:10px;font-weight:800;color:#92400e;margin-bottom:4px;">👤 Datos del destinatario</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:5px;">
                    <div><label style="font-size:10px;font-weight:700;color:#92400e;">Nombre *</label>
                        <input id="pc_dom_nombre" type="text" value="${ordenDomNombre}" placeholder="Nombre cliente" style="${inputStyle}"></div>
                    <div><label style="font-size:10px;font-weight:700;color:#92400e;">Teléfono</label>
                        <input id="pc_dom_tel" type="text" value="${clienteTelefono}" placeholder="3001234567" style="${inputStyle}"></div>
                </div>
                <div><label style="font-size:10px;font-weight:700;color:#92400e;">Dirección</label>
                    <input id="pc_dom_dir" type="text" value="${clienteDireccion}" placeholder="Calle, Carrera, Avenida..." style="${inputStyle}"></div>
               </div>`
            : `<div id="pc_datos_cliente" style="display:none;margin-bottom:8px;">
                <div style="font-size:10px;font-weight:800;color:#92400e;margin-bottom:4px;">👤 ${nombreMostrar}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:5px;">
                    <div><label style="font-size:10px;font-weight:700;color:#92400e;">Teléfono</label>
                        <input id="pc_dom_tel" type="text" value="${clienteTelefono}" placeholder="3001234567" style="${inputStyle}"></div>
                    <div></div>
                </div>
                <div><label style="font-size:10px;font-weight:700;color:#92400e;">Dirección de entrega</label>
                    <input id="pc_dom_dir" type="text" value="${clienteDireccion}" placeholder="Calle, Carrera, Avenida..." style="${inputStyle}"></div>
               </div>`;

        // Mesa de domicilio: mostrar solo el formulario domicilio sin tabs
        Swal.fire({
            title: '🛵 Datos del domicilio',
            width: '390px',
            html: `
                <input type="hidden" name="pc_tipo" value="domicilio">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px;text-align:left;">
                    ${clienteInfoHtml}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px;">
                        <div><label style="font-size:10px;font-weight:700;color:#92400e;">Costo domicilio</label>
                            <input id="pc_costo_dom" type="text" inputmode="numeric" value="${ordenDomCostoDom ? Number(ordenDomCostoDom).toLocaleString('es-CO') : ''}" style="width:100%;height:32px;border:1px solid #fde68a;border-radius:7px;padding:3px 8px;font-size:13px;font-weight:700;"></div>
                        <div><label style="font-size:10px;font-weight:700;color:#92400e;">Costo desechables</label>
                            <input id="pc_costo_dom_desech" type="text" inputmode="numeric" value="${ordenDomCostoDesech ? Number(ordenDomCostoDesech).toLocaleString('es-CO') : ''}" style="width:100%;height:32px;border:1px solid #fde68a;border-radius:7px;padding:3px 8px;font-size:13px;font-weight:700;"></div>
                    </div>
                    <div style="text-align:left;">
                        <label style="font-size:10px;font-weight:700;color:#92400e;">Observaciones del domicilio</label>
                        <textarea id="pc_observaciones" rows="2" placeholder="Indicaciones especiales, referencias, etc..." style="width:100%;border:1px solid #fde68a;border-radius:7px;padding:5px 8px;font-size:12px;resize:none;margin-top:2px;">${ordenDomObs}</textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '📤 Enviar a cocina',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            didOpen: () => {
                // Mostrar datos del cliente
                const datosCliente = document.getElementById('pc_datos_cliente');
                if (datosCliente) datosCliente.style.display = 'block';
                // Formato de miles al digitar en inputs de costo
                const fmtMiles = (input) => {
                    input.addEventListener('input', () => {
                        const raw = input.value.replace(/\./g, '').replace(/\D/g, '');
                        if (raw) input.value = Number(raw).toLocaleString('es-CO');
                        else input.value = '';
                    });
                };
                ['pc_costo_dom','pc_costo_dom_desech'].forEach(id => {
                    const el = document.getElementById(id); if (el) fmtMiles(el);
                });
            },
            preConfirm: () => {
                const parseMiles = id => parseInt((document.getElementById(id)?.value || '0').replace(/\./g,'').replace(/\D/g,'')) || 0;
                const nombre   = esConsumidorFinal ? (document.getElementById('pc_dom_nombre')?.value || '').trim() : clienteNombre;
                const telefono = ((document.getElementById('pc_dom_tel')?.value || '').trim()) || clienteTelefono;
                const direccion = ((document.getElementById('pc_dom_dir')?.value || '').trim()) || clienteDireccion;
                if (!nombre)    { Swal.showValidationMessage('El nombre del destinatario es obligatorio.'); return false; }
                if (!telefono)  { Swal.showValidationMessage('El teléfono es obligatorio.'); return false; }
                if (!direccion) { Swal.showValidationMessage('La dirección de entrega es obligatoria.'); return false; }
                const domObs = (document.getElementById('pc_observaciones')?.value || '').trim();
                return {
                    tipo_pedido: 'domicilio',
                    costo_domicilio: parseMiles('pc_costo_dom'),
                    costo_empaque: parseMiles('pc_costo_dom_desech'),
                    dom_nombre: nombre,
                    dom_telefono: telefono,
                    dom_direccion: direccion,
                    dom_observaciones: domObs || null,
                };
            }
        }).then(r => {
            if (r.isConfirmed && r.value) {
                Livewire.dispatch('mesa-enviar-cocina-confirmado', { data: r.value });
            }
        });
    };

    window.posAbrirPagoCartera = function (button, facturaId, vence) {
        if (!window.Swal) {
            alert('No se pudo abrir el modal de pago. Recargue la pagina.');
            return;
        }

        const componentEl = button.closest('[wire\\:id]');
        const componentId = componentEl ? componentEl.getAttribute('wire:id') : null;
        const component = componentId && window.Livewire ? Livewire.find(componentId) : null;

        if (!component) {
            Swal.fire('Error', 'No se encontro el componente de cartera.', 'error');
            return;
        }

        Swal.fire({
            title: 'Pagar esta factura',
            html: `
                <div style="text-align:left;max-width:340px;margin:auto;font-size:14px">
                    <div style="margin-bottom:10px;color:#6b7280">
                        Se registrara el pago total por <b>$ ${Number(vence).toLocaleString('es-CO')}</b>.
                    </div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Medio de pago</label>
                    <select id="swp_medio" class="swal2-input" style="width:100%;height:38px;padding:4px 8px;font-size:13px;margin:0 0 10px 0">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="otro">Otro</option>
                    </select>
                    <div id="swp_box_transfer" style="display:none">
                        <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Observacion de transferencia</label>
                        <input id="swp_transfer_obs" type="text" class="swal2-input" style="width:100%;height:38px;padding:4px 8px;font-size:13px;margin:0" placeholder="Ej: banco o cuenta">
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Pagar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            didOpen: () => {
                const sel = document.getElementById('swp_medio');
                const box = document.getElementById('swp_box_transfer');
                const obs = document.getElementById('swp_transfer_obs');
                const toggle = () => {
                    const isTransfer = sel && sel.value === 'transferencia';
                    if (box) box.style.display = isTransfer ? 'block' : 'none';
                    if (isTransfer && obs) obs.focus();
                };
                if (sel) sel.addEventListener('change', toggle);
                toggle();
            },
            preConfirm: () => {
                const medio = document.getElementById('swp_medio')?.value || 'efectivo';
                const obs = (document.getElementById('swp_transfer_obs')?.value || '').trim();
                if (medio === 'transferencia' && !obs) {
                    Swal.showValidationMessage('Escribe banco o cuenta para la transferencia.');
                    return false;
                }
                return {
                    monto: Number(vence),
                    medio: medio,
                    nota: 'Pago total desde Cartera',
                    transferencia_obs: medio === 'transferencia' ? obs : null
                };
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            const popup = window.open('', '_blank', 'width=420,height=700');
            if (popup) {
                popup.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Imprimiendo</title></head><body style="font:14px sans-serif;padding:12px">Generando comprobante...</body></html>');
                popup.document.close();
            }

            component.call('pagarEImprimir', facturaId, result.value)
                .then((r) => {
                    r = r || {};
                    if (r.print_url) {
                        if (popup && !popup.closed) popup.location.replace(r.print_url);
                        else window.open(r.print_url, '_blank');
                        if (r.redirect_url) setTimeout(() => { window.location.href = r.redirect_url; }, 600);
                        return;
                    }
                    if (r.html) {
                        const w = (popup && !popup.closed) ? popup : window.open('', '_blank', 'width=420,height=700');
                        if (w) {
                            w.document.open();
                            w.document.write(r.html);
                            w.document.close();
                            w.focus();
                            setTimeout(function () { try { w.print(); } catch (e) {} }, 300);
                        }
                        return;
                    }
                    if (popup && !popup.closed) popup.close();
                    Swal.fire('Error', 'No llego el comprobante para imprimir.', 'warning');
                })
                .catch((e) => {
                    if (popup && !popup.closed) popup.close();
                    console.error(e);
                    Swal.fire('Error', 'No se pudo registrar o imprimir el pago.', 'error');
                });
        });
    };

    document.addEventListener('livewire:init', () => {

        Livewire.on('open-print', (event) => {
            const url = event?.url ?? event?.[0]?.url;
            if (url) openPrintPopup(url);
        });

        Livewire.on('cliente-creado', () => {
            Swal.fire({
                icon: 'success',
                title: 'Cliente creado',
                text: 'El nuevo cliente ha sido registrado correctamente.',
                timer: 2000,
                showConfirmButton: false
            });
        });

        Livewire.on('error', (message) => {
            const text = Array.isArray(message) ? message[0] : (message?.message || message || 'Ocurrio un error.');
            Swal.fire({
                icon: 'warning',
                title: 'Atencion',
                text: text,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#4f46e5'
            });
        });

        Livewire.on('success', (message) => {
            const text = Array.isArray(message) ? message[0] : (message?.message || message || 'Proceso realizado correctamente.');
            Swal.fire({
                icon: 'success',
                title: 'Listo',
                text: text,
                timer: 1800,
                showConfirmButton: false
            });
        });
        Livewire.on('confirmar-facturar', (payload = {}) => {
            const dataEvento = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
            const domObservacionesOrden = dataEvento.dom_observaciones || '';
            const totalNumero = Number(dataEvento.totalVenta ?? @js((int) ($totalGeneral ?? 0)) ?? 0);
            const clienteVenta = dataEvento.clienteNombre || @js($clienteSeleccionadoNombre ?? 'CONSUMIDOR FINAL');
            const credito = dataEvento.creditoInfo || @js($creditoInfo ?? ['permite' => false, 'cupo_disponible' => 0, 'limite' => 0, 'deuda' => 0, 'dias' => 0]);
            const factusHabilitado = !!dataEvento.factusHabilitado;
            const cupoDisponible = Number(credito.cupo_disponible || 0);
            const deudaCliente = Number(credito.deuda || 0);
            const diasCredito = Number(credito.dias || 0);
            const creditoActivo = !!credito.permite && cupoDisponible >= totalNumero;
            const fechaCredito = new Date();
            if (diasCredito > 0) fechaCredito.setDate(fechaCredito.getDate() + diasCredito);
            const fechaVence = fechaCredito.toISOString().slice(0, 10);
            const formatMoney = (value) => '$' + Number(value || 0).toLocaleString('es-CO');
            const parseMoney = (value) => Number(String(value || '').replace(/\D/g, '') || 0);

            // Datos ya capturados al enviar a cocina (solo en flujo de mesa)
            const tipoPedidoOrden = dataEvento.tipo_pedido || 'local';
            const costoEmpaqueOrden = Number(dataEvento.costo_empaque || 0);
            const cobroDomicilioOrden = dataEvento.cobro_domicilio || 'anticipado';
            const domNombreOrden     = dataEvento.dom_nombre || null;
            const domTelefonoOrden   = dataEvento.dom_telefono || null;
            const domDireccionOrden  = dataEvento.dom_direccion || null;
            const tieneMesa = !!(dataEvento.mesa_id || @js($mesaId ?? 0));
            const esConsumidorFinal = !clienteVenta || clienteVenta.toUpperCase().includes('CONSUMIDOR FINAL') || clienteVenta.trim().toUpperCase() === 'CF';

            // Si hay extras, el total real incluye costoEmpaqueOrden (ya viene sumado en totalNumero desde PHP)
            // Pero necesitamos el total de productos solo para mostrar desglose
            const totalProductos = Number(dataEvento.totalProductos || totalNumero);

            const collectFacturaData = () => {
                const tipoFactura = document.getElementById('swal_tipo_factura').value;
                const tipoPago = document.getElementById('swal_tipo_pago').value;
                const medioPago = document.getElementById('swal_medio_pago').value;
                const obs = (document.getElementById('swal_transfer_obs').value || '').trim();
                const venc = document.getElementById('swal_fecha_venc').value;
                const recibido = parseMoney(document.getElementById('swal_monto_recibido').value);
                // Si hay mesa: tipo_pedido y costos vienen de la orden, no del modal
                const tipoPedido = tieneMesa
                    ? tipoPedidoOrden
                    : (document.querySelector('input[name="swal_tipo_pedido"]:checked')?.value || 'local');
                const costoEmpaque = tieneMesa
                    ? costoEmpaqueOrden
                    : (() => {
                        const dom = tipoPedido === 'domicilio' ? (parseFloat(document.getElementById('swal_costo_dom')?.value) || 0) : 0;
                        const desech = tipoPedido === 'domicilio'
                            ? (parseFloat(document.getElementById('swal_costo_desech')?.value) || 0)
                            : tipoPedido === 'para_llevar'
                                ? (parseFloat(document.getElementById('swal_costo_empaque_llevar')?.value) || 0)
                                : 0;
                        return dom + desech;
                    })();
                const cobroDomicilio = tieneMesa
                    ? cobroDomicilioOrden
                    : (document.querySelector('input[name="swal_cobro"]:checked')?.value || 'anticipado');

                if (tipoPago === 'credito' && !creditoActivo) {
                    Swal.showValidationMessage('Este cliente no tiene credito activo o cupo suficiente.');
                    return false;
                }
                if (tipoPago === 'credito' && !venc) {
                    Swal.showValidationMessage('Seleccione la fecha de vencimiento.');
                    return false;
                }
                if (tipoPago === 'contado' && medioPago === 'efectivo' && recibido < totalNumero) {
                    Swal.showValidationMessage('El valor recibido no puede ser menor al total.');
                    return false;
                }
                if (tipoPago === 'contado' && medioPago === 'transferencia' && !obs) {
                    Swal.showValidationMessage('Escriba la observacion de la transferencia.');
                    return false;
                }
                return {
                    tipo_factura: tipoFactura,
                    tipo_pago: tipoPago,
                    medio_pago: tipoPago === 'contado' ? medioPago : null,
                    monto_recibido: tipoPago === 'contado' && medioPago === 'efectivo' ? recibido : null,
                    vuelto: tipoPago === 'contado' && medioPago === 'efectivo' ? Math.max(0, recibido - totalNumero) : 0,
                    transferencia_obs: tipoPago === 'contado' && medioPago === 'transferencia' ? obs : '',
                    fecha_vencimiento: tipoPago === 'credito' ? venc : null,
                    tipo_pedido: tipoPedido,
                    costo_empaque: costoEmpaque,
                    cobro_domicilio: cobroDomicilio,
                    dom_nit: esConsumidorFinal ? ((document.getElementById('swal_dom_nit')?.value || '').trim() || null) : null,
                    dom_email: esConsumidorFinal ? ((document.getElementById('swal_dom_email')?.value || '').trim() || null) : null,
                    dom_razon_social: esConsumidorFinal ? ((document.getElementById('swal_dom_razon')?.value || '').trim() || null) : null,
                    dom_observaciones: domObservacionesOrden || null,
                    dom_nombre: tieneMesa
                        ? (domNombreOrden || null)
                        : ((document.getElementById('swal_dom_nombre_dest')?.value || '').trim() || null),
                    dom_telefono: tieneMesa
                        ? (domTelefonoOrden || null)
                        : ((document.getElementById('swal_dom_tel_dest')?.value || '').trim() || null),
                    dom_direccion: tieneMesa
                        ? (domDireccionOrden || null)
                        : ((document.getElementById('swal_dom_dir_dest')?.value || '').trim() || null),
                };
            };

            // Badge de tipo pedido para cuando hay mesa (dato ya capturado)
            const tipoPedidoBadges = { local: '🏠 Local', domicilio: '🛵 Domicilio', para_llevar: '🥡 Para llevar' };
            const tipoPedidoBadgeColors = { local: '#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe', domicilio: '#fffbeb;color:#92400e;border:1px solid #fde68a', para_llevar: '#f0fdf4;color:#166534;border:1px solid #86efac' };
            const badgeEstilo = tipoPedidoBadgeColors[tipoPedidoOrden] || tipoPedidoBadgeColors.local;

            // Bloque tipo pedido: badge si hay mesa, selector si no
            const tipoPedidoHtml = tieneMesa
                ? `<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <span style="font-size:11px;font-weight:800;color:#4b5563;">Pedido:</span>
                        <span style="background:${badgeEstilo};border-radius:8px;padding:3px 10px;font-size:12px;font-weight:800;">${tipoPedidoBadges[tipoPedidoOrden] || '🏠 Local'}</span>
                        ${costoEmpaqueOrden > 0 ? `<span style="font-size:11px;color:#6b7280;">· Costo extra: <b>${formatMoney(costoEmpaqueOrden)}</b></span>` : ''}
                   </div>`
                : `<div style="margin-bottom:8px;">
                        <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:6px;">¿Cómo va el pedido?</label>
                        <div style="display:flex;gap:8px;">
                            <label id="lbl_local" style="flex:1;cursor:pointer;border:2px solid #2563eb;border-radius:12px;padding:8px 6px;text-align:center;background:#eff6ff;">
                                <input type="radio" name="swal_tipo_pedido" id="tp_local" value="local" style="display:none;" checked>
                                <div style="font-size:20px;">🏠</div>
                                <div style="font-size:11px;font-weight:800;color:#1d4ed8;">Local</div>
                            </label>
                            <label id="lbl_domicilio" style="flex:1;cursor:pointer;border:2px solid #e2e8f0;border-radius:12px;padding:8px 6px;text-align:center;background:white;">
                                <input type="radio" name="swal_tipo_pedido" id="tp_domicilio" value="domicilio" style="display:none;">
                                <div style="font-size:20px;">🛵</div>
                                <div style="font-size:11px;font-weight:800;color:#6b7280;">Domicilio</div>
                            </label>
                            <label id="lbl_para_llevar" style="flex:1;cursor:pointer;border:2px solid #e2e8f0;border-radius:12px;padding:8px 6px;text-align:center;background:white;">
                                <input type="radio" name="swal_tipo_pedido" id="tp_para_llevar" value="para_llevar" style="display:none;">
                                <div style="font-size:20px;">🥡</div>
                                <div style="font-size:11px;font-weight:800;color:#6b7280;">Para llevar</div>
                            </label>
                        </div>
                   </div>
                   <div id="swal_domicilio_wrap" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:8px 10px;margin-bottom:8px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:5px;">
                            <div><label style="font-size:10px;font-weight:700;color:#92400e;">Costo domicilio</label>
                                <input id="swal_costo_dom" type="number" min="0" value="0" style="width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:13px;font-weight:700;"></div>
                            <div><label style="font-size:10px;font-weight:700;color:#92400e;">Costo desechables</label>
                                <input id="swal_costo_desech" type="number" min="0" value="0" style="width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:13px;font-weight:700;"></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:5px;">
                            <div><label style="font-size:10px;font-weight:700;color:#92400e;">Nombre destinatario</label>
                                <input id="swal_dom_nombre_dest" type="text" placeholder="Nombre" style="width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:12px;"></div>
                            <div><label style="font-size:10px;font-weight:700;color:#92400e;">Teléfono</label>
                                <input id="swal_dom_tel_dest" type="text" placeholder="3001234567" style="width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:12px;"></div>
                        </div>
                        <div style="margin-bottom:5px;"><label style="font-size:10px;font-weight:700;color:#92400e;">Dirección de entrega</label>
                            <input id="swal_dom_dir_dest" type="text" placeholder="Calle, Carrera, Avenida..." style="width:100%;height:30px;border:1px solid #fde68a;border-radius:7px;padding:3px 7px;font-size:12px;"></div>
                        <div style="display:flex;gap:6px;">
                            <label id="lbl_cobro_anticipado" style="flex:1;cursor:pointer;border:2px solid #f59e0b;border-radius:8px;padding:5px;text-align:center;background:#fef3c7;">
                                <input type="radio" name="swal_cobro" value="anticipado" style="display:none;" checked>
                                <div style="font-size:11px;font-weight:800;color:#92400e;">💰 Cobrar ahora</div>
                            </label>
                            <label id="lbl_cobro_entrega" style="flex:1;cursor:pointer;border:2px solid #e2e8f0;border-radius:8px;padding:5px;text-align:center;background:white;">
                                <input type="radio" name="swal_cobro" value="entrega" style="display:none;">
                                <div style="font-size:11px;font-weight:800;color:#6b7280;">🤝 Al entregar</div>
                            </label>
                        </div>
                   </div>
                   <div id="swal_llevar_wrap" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:8px 10px;margin-bottom:8px;">
                        <label style="font-size:11px;font-weight:700;color:#166534;">🥡 Costo empaque / desechables</label>
                        <input id="swal_costo_empaque_llevar" type="number" min="0" value="0" style="width:100%;height:30px;border:1px solid #86efac;border-radius:7px;padding:3px 8px;font-size:13px;font-weight:700;margin-top:3px;">
                   </div>`;

            // Campos ocultos para compatibilidad (datos de cliente vienen del cliente seleccionado)
            const factElecHtml = `<input id="swal_dom_nit" type="hidden"><input id="swal_dom_email" type="hidden"><input id="swal_dom_razon" type="hidden">`;

            Swal.fire({
                title: '<span style="font-size:15px;font-weight:800;color:#1f2937;">Confirmar factura</span>',
                width: '460px',
                padding: '10px 16px 12px',
                html: `
                    <div style="text-align:left;font-size:12px;color:#1f2937;">
                        ${factElecHtml}

                        {{-- Resumen cliente + total --}}
                        <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #dbeafe;border-radius:8px;padding:6px 10px;margin-bottom:6px;">
                            <div>
                                <div style="font-size:10px;color:#64748b;font-weight:700;">Cliente</div>
                                <div style="font-weight:900;color:#111827;font-size:12px;line-height:1.2;">${clienteVenta}</div>
                                ${costoEmpaqueOrden > 0 ? `<div style="font-size:10px;color:#92400e;margin-top:1px;">${tipoPedidoOrden === 'domicilio' ? '🛵' : '🥡'} Productos ${formatMoney(totalProductos)} + extra ${formatMoney(costoEmpaqueOrden)}</div>` : ''}
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:10px;color:#64748b;font-weight:700;">${costoEmpaqueOrden > 0 ? 'TOTAL A COBRAR' : 'TOTAL'}</div>
                                <b style="font-size:22px;color:#111827;white-space:nowrap;">${formatMoney(totalNumero)}</b>
                            </div>
                        </div>

                        {{-- Tipo venta / pago / medio en una fila --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:5px;margin-bottom:6px;">
                            <div>
                                <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin-bottom:2px;">Tipo venta</label>
                                <select id="swal_tipo_factura" style="width:100%;height:30px;border:1px solid #cbd5e1;border-radius:7px;padding:2px 6px;font-size:11px;background:white;">
                                    <option value="salida">Salida</option>
                                    ${factusHabilitado ? '<option value="electronica">Electronica</option>' : ''}
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin-bottom:2px;">Tipo pago</label>
                                <select id="swal_tipo_pago" style="width:100%;height:30px;border:1px solid #cbd5e1;border-radius:7px;padding:2px 6px;font-size:11px;background:white;">
                                    <option value="contado">Contado</option>
                                    ${creditoActivo ? '<option value="credito">Credito</option>' : ''}
                                </select>
                            </div>
                            <div id="swal_medio_wrap">
                                <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin-bottom:2px;">Medio pago</label>
                                <select id="swal_medio_pago" style="width:100%;height:30px;border:1px solid #cbd5e1;border-radius:7px;padding:2px 6px;font-size:11px;background:white;">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>
                        </div>

                        <div id="swal_contado_wrap">
                            <div id="swal_efectivo_wrap">
                                <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin-bottom:2px;">Valor recibido</label>
                                <input id="swal_monto_recibido" type="text" inputmode="numeric" value="" placeholder="0"
                                    style="display:block;width:100%;height:36px;border:2px solid #a5b4fc;border-radius:8px;padding:4px 10px;text-align:center;font-weight:900;font-size:20px;margin-bottom:4px;" autocomplete="off">
                                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-bottom:4px;">
                                    <button type="button" data-cash-exact="1"    style="border:0;border-radius:6px;background:#2563eb;color:white;font-weight:800;font-size:11px;padding:5px 2px;">Exacto</button>
                                    <button type="button" data-cash-add="5000"   style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+5.000</button>
                                    <button type="button" data-cash-add="10000"  style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+10.000</button>
                                    <button type="button" data-cash-add="20000"  style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+20.000</button>
                                    <button type="button" data-cash-add="50000"  style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+50.000</button>
                                    <button type="button" data-cash-add="100000" style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+100.000</button>
                                    <button type="button" data-cash-add="200000" style="border:0;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:5px 2px;">+200.000</button>
                                    <button type="button" data-cash-clear="1"   style="border:0;border-radius:6px;background:#f3f4f6;color:#374151;font-weight:800;font-size:11px;padding:5px 2px;">Limpiar</button>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;background:#f0fdf4;border:1px solid #86efac;border-radius:7px;padding:5px 10px;">
                                    <span style="font-size:12px;font-weight:800;color:#166534;">Vuelto</span>
                                    <b id="swal_vuelto" style="font-size:20px;color:#15803d;">$0</b>
                                </div>
                            </div>
                            <div id="swal_transfer_wrap" style="display:none;">
                                <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin-bottom:2px;">Observacion de transferencia</label>
                                <textarea id="swal_transfer_obs" rows="2" placeholder="Banco, referencia o detalle" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:5px 8px;resize:none;font-size:12px;"></textarea>
                            </div>
                        </div>

                        <div id="swal_credito_wrap" style="display:none;">
                            <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:6px 10px;color:#3730a3;font-size:11px;">
                                <div style="display:flex;justify-content:space-between;"><span>Cupo disponible</span><b>${formatMoney(cupoDisponible)}</b></div>
                                <div style="display:flex;justify-content:space-between;"><span>Deuda actual</span><b>${formatMoney(deudaCliente)}</b></div>
                                <div style="display:flex;justify-content:space-between;"><span>Dias credito</span><b>${diasCredito}</b></div>
                            </div>
                            <label style="display:block;font-size:10px;font-weight:700;color:#4b5563;margin:4px 0 2px;">Fecha vencimiento</label>
                            <input id="swal_fecha_venc" type="date" value="${fechaVence}" style="width:100%;height:30px;border:1px solid #cbd5e1;border-radius:7px;padding:2px 8px;font-size:12px;">
                        </div>

                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:${tipoPedidoOrden === 'domicilio' ? 'default' : 'pointer'};user-select:none;font-size:13px;color:#374151;font-weight:600;">
                            <input type="checkbox" id="swal_imprimir" ${tipoPedidoOrden === 'domicilio' ? 'checked disabled' : ''}
                                style="width:16px;height:16px;accent-color:#4f46e5;cursor:${tipoPedidoOrden === 'domicilio' ? 'not-allowed' : 'pointer'};">
                            🖨️ Imprimir comprobante${tipoPedidoOrden === 'domicilio' ? ' <span style="font-size:10px;color:#f59e0b;font-weight:700;">(obligatorio para domicilio)</span>' : ''}
                        </label>
                    </div>
                `,
                showCancelButton: true,
                showDenyButton: false,
                confirmButtonText: 'Facturar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#ef4444',
                reverseButtons: true,
                didOpen: () => {
                    const tipoPago = document.getElementById('swal_tipo_pago');
                    const medioPago = document.getElementById('swal_medio_pago');
                    const contadoWrap = document.getElementById('swal_contado_wrap');
                    const creditoWrap = document.getElementById('swal_credito_wrap');
                    const efectivoWrap = document.getElementById('swal_efectivo_wrap');
                    const transferWrap = document.getElementById('swal_transfer_wrap');
                    const montoRecibido = document.getElementById('swal_monto_recibido');
                    const vuelto = document.getElementById('swal_vuelto');

                    // Lógica tipo de pedido (solo en venta directa, no en mesa)
                    if (!tieneMesa) {
                        const domWrap = document.getElementById('swal_domicilio_wrap');
                        const llevarWrap = document.getElementById('swal_llevar_wrap');
                        const labels = { local: 'lbl_local', domicilio: 'lbl_domicilio', para_llevar: 'lbl_para_llevar' };
                        const syncTipoPedido = () => {
                            const val = document.querySelector('input[name="swal_tipo_pedido"]:checked')?.value || 'local';
                            if (domWrap) domWrap.style.display = val === 'domicilio' ? 'block' : 'none';
                            if (llevarWrap) llevarWrap.style.display = val === 'para_llevar' ? 'block' : 'none';
                            Object.entries(labels).forEach(([k, id]) => {
                                const el = document.getElementById(id);
                                if (!el) return;
                                const active = k === val;
                                el.style.borderColor = active ? (k === 'domicilio' ? '#f59e0b' : k === 'para_llevar' ? '#22c55e' : '#2563eb') : '#e2e8f0';
                                el.style.background = active ? (k === 'domicilio' ? '#fffbeb' : k === 'para_llevar' ? '#f0fdf4' : '#eff6ff') : 'white';
                                el.querySelector('div:last-child').style.color = active ? (k === 'domicilio' ? '#92400e' : k === 'para_llevar' ? '#166534' : '#1d4ed8') : '#6b7280';
                            });
                        };
                        document.querySelectorAll('input[name="swal_tipo_pedido"]').forEach(r => r.addEventListener('change', syncTipoPedido));
                        document.querySelectorAll('label[id^="lbl_"]').forEach(lbl => lbl.addEventListener('click', () => {
                            const radio = lbl.querySelector('input[type="radio"]');
                            if (radio && radio.name === 'swal_tipo_pedido') { radio.checked = true; syncTipoPedido(); }
                        }));
                        syncTipoPedido();

                        // Radios cobro domicilio
                        const syncCobro = () => {
                            const val = document.querySelector('input[name="swal_cobro"]:checked')?.value || 'anticipado';
                            const la = document.getElementById('lbl_cobro_anticipado');
                            const le = document.getElementById('lbl_cobro_entrega');
                            if (la) { la.style.borderColor = val === 'anticipado' ? '#f59e0b' : '#e2e8f0'; la.style.background = val === 'anticipado' ? '#fef3c7' : 'white'; la.querySelector('div').style.color = val === 'anticipado' ? '#92400e' : '#6b7280'; }
                            if (le) { le.style.borderColor = val === 'entrega' ? '#2563eb' : '#e2e8f0'; le.style.background = val === 'entrega' ? '#eff6ff' : 'white'; le.querySelector('div').style.color = val === 'entrega' ? '#1d4ed8' : '#6b7280'; }
                        };
                        document.querySelectorAll('input[name="swal_cobro"]').forEach(r => r.addEventListener('change', syncCobro));
                        document.querySelectorAll('#lbl_cobro_anticipado, #lbl_cobro_entrega').forEach(lbl => lbl.addEventListener('click', () => {
                            const radio = lbl.querySelector('input[type="radio"]');
                            if (radio) { radio.checked = true; syncCobro(); }
                        }));
                        syncCobro();
                    }

                    const formatearMontoInput = (valor) => {
                        const numero = parseMoney(valor);
                        montoRecibido.value = numero > 0 ? formatMoney(numero).replace('$', '').trim() : '';
                        return numero;
                    };

                    const actualizarVuelto = () => {
                        const recibido = parseMoney(montoRecibido.value);
                        vuelto.textContent = formatMoney(Math.max(0, recibido - totalNumero));
                    };

                    const medioWrap = document.getElementById('swal_medio_wrap');
                    const sync = () => {
                        const esCredito = tipoPago.value === 'credito';
                        const esTransferencia = medioPago.value === 'transferencia';
                        if (medioWrap) medioWrap.style.display = esCredito ? 'none' : 'block';
                        contadoWrap.style.display = esCredito ? 'none' : 'block';
                        creditoWrap.style.display = esCredito ? 'block' : 'none';
                        efectivoWrap.style.display = (!esCredito && !esTransferencia) ? 'block' : 'none';
                        transferWrap.style.display = (!esCredito && esTransferencia) ? 'block' : 'none';
                        actualizarVuelto();
                    };

                    montoRecibido.addEventListener('input', () => {
                        formatearMontoInput(montoRecibido.value);
                        actualizarVuelto();
                    });
                    document.querySelectorAll('[data-cash-add]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const actual = parseMoney(montoRecibido.value);
                            const suma = Number(button.getAttribute('data-cash-add') || 0);
                            formatearMontoInput(String(actual + suma));
                            actualizarVuelto();
                        });
                    });
                    document.querySelectorAll('[data-cash-exact]').forEach((button) => {
                        button.addEventListener('click', () => {
                            formatearMontoInput(String(totalNumero));
                            actualizarVuelto();
                        });
                    });
                    document.querySelectorAll('[data-cash-clear]').forEach((button) => {
                        button.addEventListener('click', () => {
                            montoRecibido.value = '';
                            actualizarVuelto();
                            montoRecibido.focus();
                        });
                    });
                    tipoPago.addEventListener('change', sync);
                    medioPago.addEventListener('change', sync);
                    sync();
                },
                preConfirm: collectFacturaData,
            }).then((result) => {
                if (!result.isConfirmed) return;

                const imprimirEl = document.getElementById('swal_imprimir');
                const imprimir = imprimirEl ? (imprimirEl.checked || imprimirEl.disabled) : false;

                if (!imprimir) {
                    Livewire.dispatch('facturar-confirmada', { data: result.value });
                    return;
                }

                if (imprimir) {
                    const componentId = @js($this->getId());
                    const component = componentId && window.Livewire ? Livewire.find(componentId) : null;
                    if (!component) {
                        Swal.fire('Error', 'No se encontro el componente para imprimir.', 'error');
                        return;
                    }

                    const popup = window.open('', '_blank', 'width=420,height=700');
                    if (popup) {
                        popup.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Imprimiendo</title></head><body style="font:14px sans-serif;padding:12px">Generando factura...</body></html>');
                        popup.document.close();
                    }

                    component.call('facturarEImprimir', result.value)
                        .then((r) => {
                            r = r || {};
                            if (r.print_url) {
                                if (popup && !popup.closed) popup.location.replace(r.print_url);
                                else window.open(r.print_url, '_blank');
                                if (r.redirect_url) setTimeout(() => { window.location.href = r.redirect_url; }, 600);
                                return;
                            }
                            if (popup && !popup.closed) popup.close();
                            Swal.fire('Error', r.error || 'No llego la factura para imprimir.', 'warning');
                        })
                        .catch((e) => {
                            if (popup && !popup.closed) popup.close();
                            console.error(e);
                            Swal.fire('Error', 'No se pudo facturar e imprimir.', 'error');
                        });
                }
            });
        });
        Livewire.on('prefactura-borrada', () => {
            Swal.fire({
                icon: 'success',
                title: 'Prefactura eliminada',
                text: 'La prefactura se elimino correctamente.',
                timer: 1500,
                showConfirmButton: false
            });
        });

        Livewire.on('confirmar-borrar-prefactura', () => {
            Swal.fire({
                title: 'Borrar prefactura?',
                text: 'Esta accion eliminara la prefactura actual.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Si, borrar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('borrar-prefactura-confirmada');
                } else {
                    Livewire.dispatch('reiniciar-prefactura');
                }
            });
        });

        Livewire.on('confirmar-guardar-prefactura', () => {
            Swal.fire({
                title: 'Guardar prefactura?',
                text: 'Se guardara la venta actual como prefactura.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, guardar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('guardar-prefactura-confirmada');
                }
            });
        });

        Livewire.on('mostrar-carrito-vacio', () => {
            Swal.fire({ icon: 'warning', title: 'Carrito vacío', text: 'Agregue productos primero.' });
        });

        Livewire.on('mostrar-cliente-requerido', () => {
            Swal.fire({ icon: 'warning', title: 'Cliente requerido', text: 'Seleccione un cliente antes de guardar.' });
        });

    });
</script>

<script>
function abrirIngresoTaller(nombreCliente, telefonoCliente) {
    if (!document.getElementById('taller-modal-style')) {
        const style = document.createElement('style');
        style.id = 'taller-modal-style';
        style.textContent = `
            .swal-taller-popup { display:flex !important; flex-direction:column; max-height:92vh !important; }
            .swal-taller-html { overflow-y:auto; max-height:calc(92vh - 160px); margin:0 !important; }
        `;
        document.head.appendChild(style);
    }

    nombreCliente = (nombreCliente || '').trim();
    telefonoCliente = (telefonoCliente || '').trim();

    if (!nombreCliente || nombreCliente.toUpperCase().includes('CONSUMIDOR FINAL')) {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona un cliente primero',
            text: 'Debes buscar y seleccionar el cliente antes de crear un ingreso al taller.',
            confirmButtonColor: '#0f766e',
        });
        return;
    }

    Swal.fire({
        title: '🔧 Ingreso al taller',
        width: '600px',
        heightAuto: false,
        customClass: { popup: 'swal-taller-popup', htmlContainer: 'swal-taller-html' },
        html: `
<div style="text-align:left;padding:4px 0;">

  <div style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;border-bottom:2px solid #ccfbf1;padding-bottom:4px;">👤 Cliente seleccionado</div>
  <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:10px 14px;margin-bottom:10px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:18px;">👤</span>
    <div>
      <div style="font-size:13px;font-weight:700;color:#166534;" id="t_nombre_display">${nombreCliente}</div>
      <div style="font-size:11px;color:#4b7a5e;">Cliente ya registrado en el sistema</div>
    </div>
  </div>
  <input type="hidden" id="t_nombre" value="${nombreCliente}">
  <input type="hidden" id="t_telefono" value="${telefonoCliente.replace(/"/g, '&quot;')}">

  <datalist id="dl_marcas">
    <!-- Motos -->
    <option value="Yamaha"><option value="Honda"><option value="Suzuki"><option value="Kawasaki">
    <option value="AKT"><option value="TVS"><option value="Bajaj"><option value="Hero">
    <option value="Royal Enfield"><option value="KTM"><option value="Benelli"><option value="Ducati">
    <option value="BMW Motorrad"><option value="Harley-Davidson"><option value="Triumph">
    <!-- Carros -->
    <option value="Chevrolet"><option value="Renault"><option value="Toyota"><option value="Mazda">
    <option value="Kia"><option value="Hyundai"><option value="Ford"><option value="Volkswagen">
    <option value="Nissan"><option value="Mitsubishi"><option value="Jeep"><option value="Dodge">
    <option value="Ram"><option value="Volvo"><option value="Peugeot"><option value="Citroën">
    <option value="Mercedes-Benz"><option value="Audi"><option value="BMW"><option value="BYD">
    <option value="Chery"><option value="DFSK"><option value="JAC"><option value="Haval">
  </datalist>
  <datalist id="dl_modelos">
    <!-- Motos frecuentes -->
    <option value="XTZ 150"><option value="XTZ 125"><option value="FZ 16"><option value="FZ25">
    <option value="R15 V3"><option value="MT-03"><option value="NMAX 155"><option value="PCX 150">
    <option value="CB 190R"><option value="CB 125F"><option value="Wave 110"><option value="Biz 125">
    <option value="Titan 150"><option value="CG 150"><option value="Gixxer 150"><option value="GN 125">
    <option value="AX 100"><option value="EN 125"><option value="GS 150"><option value="Pulsar NS 200">
    <option value="Pulsar 200 NS"><option value="Discover 125"><option value="Platina 100">
    <option value="Apache RTR 160"><option value="Duke 200"><option value="Duke 390">
    <option value="Leoncino 250"><option value="TNT 300"><option value="CB650R">
    <!-- Carros frecuentes -->
    <option value="Spark"><option value="Sail"><option value="Onix"><option value="Tracker">
    <option value="Captiva"><option value="Duster"><option value="Logan"><option value="Sandero">
    <option value="Kwid"><option value="Stepway"><option value="Corolla"><option value="Hilux">
    <option value="Land Cruiser"><option value="Fortuner"><option value="Mazda 3"><option value="CX-5">
    <option value="Rio"><option value="Picanto"><option value="Sportage"><option value="Tucson">
    <option value="Accent"><option value="Santa Fe"><option value="EcoSport"><option value="Ranger">
    <option value="Jetta"><option value="Golf"><option value="Tiguan"><option value="Frontier">
    <option value="Sentra"><option value="Kicks"><option value="Lancer"><option value="Outlander">
  </datalist>

  <div style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;border-bottom:2px solid #ccfbf1;padding-bottom:4px;">🏍️🚗 Datos del vehículo</div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px;">
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Placa * <span style="font-weight:400;color:#9ca3af;">(sin guion)</span></label>
      <input id="t_placa" type="text" placeholder="ABC123 / ABC12"
        maxlength="7"
        oninput="this.value=this.value.replace(/-/g,'').toUpperCase()"
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:15px;font-weight:800;text-transform:uppercase;box-sizing:border-box;letter-spacing:.12em;">
    </div>
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Marca <span style="font-weight:400;color:#9ca3af;">(moto o carro)</span></label>
      <input id="t_marca" type="text" list="dl_marcas" placeholder="Yamaha, Honda, Chevrolet..."
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
    </div>
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Modelo <span style="font-weight:400;color:#9ca3af;">(moto o carro)</span></label>
      <input id="t_modelo" type="text" list="dl_modelos" placeholder="XTZ 150, Onix, Duster..."
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px;">
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Color</label>
      <input id="t_color" type="text" placeholder="Blanco, Rojo, Negro..."
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
    </div>
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Kilometraje</label>
      <input id="t_km" type="number" placeholder="45000"
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
    </div>
    <div>
      <label style="font-size:11px;color:#6b7280;font-weight:600;display:block;margin-bottom:3px;">Año</label>
      <input id="t_anio" type="number" placeholder="2020" min="1980" max="2030"
        style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
    </div>
  </div>

  <div style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;border-bottom:2px solid #ccfbf1;padding-bottom:4px;">🔍 Diagnóstico / trabajo solicitado</div>
  <textarea id="t_diag" placeholder="Describe el problema, síntomas, trabajo a realizar..." rows="3"
    style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;resize:vertical;box-sizing:border-box;margin-bottom:8px;"></textarea>
  <textarea id="t_obs" placeholder="Observaciones: accesorios, estado de entrega, condición del vehículo..." rows="2"
    style="width:100%;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>

</div>`,
        showCancelButton: true,
        confirmButtonText: '✅ Crear ingreso y agregar productos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0f766e',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        focusConfirm: false,
        didOpen: () => { document.getElementById('t_placa')?.focus(); },
        preConfirm: () => {
            const placa = (document.getElementById('t_placa').value || '').trim();
            if (!placa) { Swal.showValidationMessage('La placa del vehículo es obligatoria.'); return false; }
            return {
                nombre:        nombreCliente,
                telefono:      (document.getElementById('t_telefono').value || '').trim(),
                placa:         placa.toUpperCase(),
                marca:         (document.getElementById('t_marca').value  || '').trim(),
                modelo:        (document.getElementById('t_modelo').value || '').trim(),
                color:         (document.getElementById('t_color').value  || '').trim(),
                km:            (document.getElementById('t_km').value     || '').trim(),
                diagnostico:   (document.getElementById('t_diag').value   || '').trim(),
                observaciones: (document.getElementById('t_obs').value    || '').trim(),
            };
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const d = result.value;
            Livewire.dispatch('crear-orden-taller', {
                clienteNombre:   d.nombre,
                clienteTelefono: d.telefono,
                placa:           d.placa,
                marca:           d.marca,
                modelo:          d.modelo,
                color:           d.color,
                km:              d.km,
                diagnostico:     d.diagnostico,
                observaciones:   d.observaciones,
            });
        }
    });
}
</script>
