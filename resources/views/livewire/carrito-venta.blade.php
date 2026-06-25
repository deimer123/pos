<div class="pos-cart-component" style="display: flex; flex-direction: column; {{ $mesaId ? 'min-height: 100%' : 'height: 100%; min-height: 0;' }}">
    

    <div class="pos-cart-header" style="padding: 1rem; border-bottom: 1px solid #ddd;">
        <div class="flex items-center gap-2">
            <input type="text"
                class="flex-1 text-base bg-gray-100 border border-gray-300 rounded-full px-4 py-2 text-gray-700 font-medium cursor-not-allowed"
                value="{{ $clienteSeleccionadoNombre ?? 'Nombre Cliente' }}" disabled />

            <button wire:click="abrirModalBuscarCliente"
                class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm transition">

                Buscar Cliente
            </button>


            <div class="flex items-center gap-2">
                @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa')) 
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



    
    <div class="pos-cart-table-scroll flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-1 py-1"
        style="font-size: 12px; line-height: 1.1;">
        <table class="w-full table-fixed bg-white border">
            <thead class="sticky top-0 z-20 bg-gray-100 text-gray-700">
                <tr class="border-b">
                    <th class="px-1 py-1 text-left w-14">Codigo</th>
                    <th class="px-1 py-1 text-left">Producto</th>
                    <th class="px-1 py-1 text-center w-14">Cant.</th>
                    <th class="px-1 py-1 text-center w-20">Precio</th>
                    <th class="px-1 py-1 text-center w-16">Subtotal</th>
                    <th class="px-1 py-1 text-center w-24">Accion</th>
                </tr>
            </thead>

            <tbody>
                @forelse($carrito as $id => $item)
                    @php $enviado = !empty($item['enviado_cocina']); @endphp
                    <tr class="border-b {{ $enviado ? 'bg-gray-100 opacity-70' : 'hover:bg-indigo-50' }}">
                        @php
                            $permiteDecimal = (bool) ($item['permite_decimal'] ?? ((int) ($item['id_unidad_de_medida'] ?? 1) !== 1));
                        @endphp
                        <td class="px-1 py-0.5 border">{{ $item['id_producto'] ?? '-' }}</td>
                        @php
                            $stockUnidadCarrito = match ($item['vende_por'] ?? 'unidad') {
                                'peso' => 'kg',
                                'porcion' => 'porcion',
                                'litro' => 'lt',
                                'metro' => 'mt',
                                'hora' => 'hr',
                                default => ((bool) ($item['permite_decimal'] ?? false) ? 'kg' : 'und'),
                            };
                            $stockDecimalesCarrito = $stockUnidadCarrito === 'und' ? 0 : 2;
                            // Si tiene receta, mostrar porciones disponibles por ingredientes
                            if (!empty($item['porciones_receta'])) {
                                $stockValorCarrito  = (float) $item['porciones_receta']['porciones'];
                                $stockUnidadCarrito = $item['porciones_receta']['unidad'];
                                $stockDecimalesCarrito = 2;
                            } else {
                                $stockValorCarrito = (float) ($item['existencias'] ?? 0);
                            }
                            $stockTooltipCarrito = number_format($stockValorCarrito, $stockDecimalesCarrito, ',', '.') . ' ' . $stockUnidadCarrito;
                        @endphp
                        <td class="px-1 py-0.5 border {{ $stockValorCarrito <= 0 ? 'text-red-600 font-semibold' : '' }}"
                            title="Existencias: {{ $stockTooltipCarrito }}">
                            <button type="button"
                                title="{{ $item['nombre'] }} | Stock: {{ $stockTooltipCarrito }}"
                                @click="$dispatch('ver-nombre-carrito-mobile', { nombre: @js($item['nombre']), stock: @js($stockTooltipCarrito) })"
                                class="block w-full truncate text-left">
                                {{ $item['nombre'] }}
                                @if($enviado)
                                    <span style="font-size:9px; background:#22c55e; color:white; border-radius:4px; padding:1px 4px; margin-left:3px;">✓ Cocina</span>
                                @endif
                                @if(!empty($item['combo_activo']))
                                    <span style="font-size:9px; background:#7c3aed; color:white; border-radius:4px; padding:1px 5px; margin-left:3px;">🎁 {{ $item['combo_activo'] }}</span>
                                @endif
                            </button>
                        </td>
                        <td class="px-1 py-0.5 border text-center">
                            <input type="number"
                                min="{{ $permiteDecimal ? '0.01' : '1' }}"
                                step="{{ $permiteDecimal ? '0.01' : '1' }}"
                                inputmode="{{ $permiteDecimal ? 'decimal' : 'numeric' }}"
                                wire:model.lazy="carrito.{{ $id }}.cantidad"
                                wire:change="actualizarTotales"
                                wire:key="cantidad-{{ $id }}-{{ $item['cantidad'] }}"
                                class="border rounded text-center"
                                style="width: 58px; height: 24px; font-size: 11px; padding: 0 3px;"
                                {{ $enviado ? 'disabled' : '' }} />
                        </td>
                        <td class="px-1 py-0.5 border text-center whitespace-nowrap">
                            <div class="whitespace-nowrap text-[11px] leading-none">
                                ${{ number_format(round($item['nuevo_precio'] ?? ($item['precio'] ?? 0)), 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="px-1 py-0.5 border text-center font-semibold text-indigo-600 whitespace-nowrap">
                            ${{ number_format($item['total'] ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-1 py-0.5 border text-center">
                            <div class="flex items-center justify-center gap-1">
                            @if(! $enviado)
                              <button x-data
    x-on:click="$wire.abrirModalRenombrar('{{ $item['uuid'] ?? $item['id_producto'] }}')"
    class="text-indigo-600 hover:text-indigo-800 transition"
    title="Editar">

    <svg xmlns="http://www.w3.org/2000/svg"
        class="w-5 h-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor">

        <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 9.586-9.586z"/>
    </svg>

</button>

    <button x-data="{ uuid: @js($item['uuid'] ?? $item['id_producto']) }"

    x-on:click="
        Swal.fire({
            title:'Borrar?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Si',
            cancelButtonText:'No'
        }).then(r=>{
            if(r.isConfirmed){
                $wire.eliminarDelCarrito(uuid)
            }
        })
    "

    class="text-red-600 hover:text-red-800 transition"
    title="Eliminar">

    <svg xmlns="http://www.w3.org/2000/svg"
        class="w-5 h-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor">

        <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M19 7l-.867 12.142A2.25 2.25 0 0115.891 21H8.109a2.25 2.25 0 01-2.242-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8"/>
    </svg>

</button>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-gray-500 py-4">No hay productos en el carrito.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>



    

    <div class="pos-cart-total-bar px-3 py-2 border-t bg-white flex items-center justify-between">
        @if($mesaId)
            {{-- Enviar cocina y Facturar: visibles siempre (desktop Y móvil) --}}
            <div class="flex gap-2 flex-shrink-0">
                <button onclick="window.Livewire.dispatch('mesa-enviar-cocina')"
                    style="background:#2563eb; color:white; border:none; border-radius:9999px; padding:0 14px; height:34px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">
                    📤 Enviar cocina
                </button>
                @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
                <button onclick="window.Livewire.dispatch('mesa-facturar')"
                    style="background:#16a34a; color:white; border:none; border-radius:9999px; padding:0 14px; height:34px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">
                    💳 Facturar
                </button>
                @endif
            </div>
        @else
        @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
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
        <button
            x-on:click="
                        if (($wire.get('carrito') ?? []).length === 0) {
                          Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de editar.'});
                        } else { $wire.abrirModalEditar(); }
                      "
            class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
            Editar
        </button>
        @endif

        <div class="flex gap-2">

            @if(! $mesaId)
            <button wire:click="abrirModalCrearCliente"
                class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                + Crear Cliente
            </button>
            @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
                @if ($cajaEstado === 'abierta')
                    <button type="button"
                        class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                        wire:click="abrirMovimientoCajaModal('salida')">
                        Entrada / salida
                    </button>
                @endif
                <button type="button"
                    class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                    wire:click="abrirModalCartera">
                    Cartera
                </button>
            @endif
            @endif

            @if($mesaId)
            @php $esMesero = auth()->user()->hasRole('mesero') && ! auth()->user()->hasAnyRole(['cajero','admin_empresa','vendedor']); @endphp
            @if(! $esMesero)
            {{-- AFUERA: Editar, Liberar, Espera, Cuenta --}}
            <button
                x-on:click="
                    if (($wire.get('carrito') ?? []).length === 0) {
                      Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de editar.'});
                    } else { $wire.abrirModalEditar(); }
                "
                class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                Editar
            </button>
            <button
                x-on:click="Swal.fire({
                          title: '¿Liberar mesa?', text: 'Se cancelará la comanda y se liberará la mesa.',
                          icon: 'warning', showCancelButton: true,
                          confirmButtonColor: '#dc2626', confirmButtonText: 'Sí, liberar', cancelButtonText: 'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-liberar'); }})"
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full pos-hide-mobile"
                style="background:#dc2626;">
                🔓 Liberar
            </button>
            <button
                x-on:click="Swal.fire({
                          title: '¿Poner en espera?', text: 'La cuenta se guarda y la mesa queda libre.',
                          icon: 'question', showCancelButton: true,
                          confirmButtonColor: '#d97706', confirmButtonText: 'Sí, en espera', cancelButtonText: 'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-en-espera'); }})"
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full pos-hide-mobile"
                style="background:#d97706;">
                ⏸ Espera
            </button>
            <button
                onclick="window.open('/pos/mesa/{{ $mesaId }}/cuenta', '_blank', 'width=420,height=680')"
                class="pos-cart-main-action text-white text-xs px-3 h-8 rounded-full pos-hide-mobile"
                style="background:#374151;">
                🖨️ Cuenta
            </button>

            {{-- BOTÓN ACCIONES: Entrada/salida, Cartera, Ver --}}
            <div class="pos-cart-secondary-action" x-data="{ openAcc: false }" style="position:relative;">
                <button type="button"
                    @click="openAcc = !openAcc"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow flex items-center gap-1">
                    Acciones ▾
                </button>
                <div x-show="openAcc" x-cloak @click.outside="openAcc = false"
                    style="position:absolute; bottom:calc(100% + 6px); right:0; background:white; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:160px; z-index:9999; display:flex; flex-direction:column; overflow:hidden;">
                    @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
                        @if ($cajaEstado === 'abierta')
                        <button type="button"
                            @click.stop="openAcc = false; $wire.abrirMovimientoCajaModal('salida')"
                            style="padding:10px 16px; text-align:left; font-size:12px; background:white; border:none; cursor:pointer; border-bottom:1px solid #f1f5f9;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                            📥 Entrada / salida
                        </button>
                        @endif
                        <button type="button"
                            @click.stop="openAcc = false; $wire.abrirModalCartera()"
                            style="padding:10px 16px; text-align:left; font-size:12px; background:white; border:none; cursor:pointer; border-bottom:1px solid #f1f5f9;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                            💼 Cartera
                        </button>
                    @endif
                    <button type="button"
                        @click.stop="openAcc = false; $wire.verPrefacturas()"
                        style="padding:10px 16px; text-align:left; font-size:12px; background:white; border:none; cursor:pointer;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        🗒️ Ver prefacturas
                    </button>
                </div>
            </div>
            @endif {{-- fin !$esMesero --}}
            @else
            <button
                x-on:click="Swal.fire({
                          title:'Vaciar carrito?', text:'Se eliminaran todos los productos.',
                          icon:'warning', showCancelButton:true, confirmButtonText:'Si, vaciar', cancelButtonText:'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.limpiarCarrito(); }})"
                class="pos-cart-main-action bg-red-600 hover:bg-red-700 text-white text-xs px-3 h-8 rounded-full">
                Limpiar
            </button>
            @endif

            @if(! $mesaId)
            <button
                x-on:click="
                          if (($wire.get('carrito') ?? []).length === 0) {
                            Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de guardar.'});
                          } else {
                            Swal.fire({
                              title:'Guardar prefactura?', icon:'question', showCancelButton:true,
                              confirmButtonText:'Si, guardar', cancelButtonText:'Cancelar'
                            }).then(r=>{ if(r.isConfirmed){ $wire.guardarPrefacturaConfirmada(); }});
                          }
                        "
                class="pos-cart-main-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                Guardar
            </button>

            <button wire:click="verPrefacturas"
                class="pos-cart-secondary-action bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                Ver
            </button>
            @endif
        </div>

        @if($mesaId)
        {{-- Menú ☰ de mesa: Liberar, Espera, Cuenta --}}
        <div class="pos-cart-mobile-more pos-cart-mesa-actions" x-data="{ open: false }" wire:key="mobile-actions-mesa-root">
        <button type="button" class="pos-cart-mobile-more-button pos-cart-mesa-actions-btn" @click="open = !open">☰</button>
        <div class="pos-cart-mobile-more-menu" x-show="open" x-cloak @click.stop @click.outside="open = false">
            <button type="button" class="pos-cart-menu-item" style="background:#dc2626;color:white;" wire:key="mesa-mobile-liberar"
                @click.prevent.stop="open = false; Swal.fire({
                    title: '¿Liberar mesa?', text: 'Se cancelará la comanda y se liberará la mesa.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#dc2626', confirmButtonText: 'Sí, liberar', cancelButtonText: 'Cancelar'
                }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-liberar'); }})">
                🔓 Liberar
            </button>
            <button type="button" class="pos-cart-menu-item" style="background:#d97706;color:white;" wire:key="mesa-mobile-espera"
                @click.prevent.stop="open = false; Swal.fire({
                    title: '¿Poner en espera?', text: 'La mesa quedará en estado de espera (amarillo).',
                    icon: 'question', showCancelButton: true,
                    confirmButtonColor: '#d97706', confirmButtonText: 'Sí, en espera', cancelButtonText: 'Cancelar'
                }).then(r=>{ if(r.isConfirmed){ $wire.dispatch('mesa-en-espera'); }})">
                ⏸ En espera
            </button>
            <button type="button" class="pos-cart-menu-item" style="background:#374151;color:white;" wire:key="mesa-mobile-cuenta"
                @click.prevent.stop="open = false; window.open('/pos/mesa/{{ $mesaId }}/cuenta', '_blank', 'width=420,height=680')">
                🖨️ Cuenta
            </button>
        </div>
        </div>
        @endif

    </div>{{-- /pos-desktop-cart-actions --}}

    @if(! $mesaId)
    <div class="pos-cart-mobile-more" x-data="{ open: false }" wire:key="mobile-actions-root-{{ $cajaEstado }}">
        <button type="button" class="pos-cart-mobile-more-button" @click="open = !open">
                Acciones
            </button>

            <div class="pos-cart-mobile-more-menu" x-show="open" x-cloak @click.stop @click.outside="open = false" wire:key="mobile-actions-menu-{{ $cajaEstado }}">
                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-edit" wire:key="mobile-action-editar"
                    @click.prevent.stop="
                        open = false;
                        if (($wire.get('carrito') ?? []).length === 0) {
                          Swal.fire({icon:'warning', title:'Carrito vacio', text:'Debe agregar productos antes de editar.'});
                        } else { $wire.abrirModalEditar(); }
                    ">
                    Editar
                </button>

                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-create-client" wire:key="mobile-action-crear-cliente" @click.prevent.stop="open = false; $wire.abrirModalCrearCliente();">
                    Crear Cliente
                </button>

                @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
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

                @if(! $mesaId)
                <button type="button" class="pos-cart-menu-item pos-cart-menu-item-view" wire:key="mobile-action-ver" @click.prevent.stop="open = false; $wire.verPrefacturas();">
                    Ver
                </button>
                @endif

                @if($mesaId)
                <button type="button" class="pos-cart-menu-item" style="background:#16a34a;color:white;" wire:key="mobile-action-cocina" @click.prevent.stop="open = false; $wire.dispatch('mesa-enviar-cocina');">
                    📤 Enviar cocina
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
                                        <input type="number" step="1" x-ref="nuevoPrecio{{ $id }}"
                                            x-model.number="nuevoPrecio"
                                            @input="
                            nuevoPrecio = Math.round(nuevoPrecio);
                            recalculaPorPrecio();
                            $dispatch('recalcular-total');
                        "
                                            @blur="
                            nuevoPrecio = Math.round(nuevoPrecio);
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
                                    nuevo_precio: parseFloat(nuevo) || 0,
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
                                @if(! $mesaId)
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
                                                        <td class="p-2 border text-center">{{ $d['producto_id'] }}
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

                                        @if (auth()->user()->hasRole('cajero') || auth()->user()->hasRole('admin_empresa'))
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
                                                <td class="px-3 py-2">{{ $row['producto_id'] }}</td>
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

                <div class="pos-cierre-resumen mb-3 bg-white rounded-lg p-3 border text-xs overflow-y-auto shadow-sm"
                    style="text-align:left;max-height:34vh;border-color:#dbeafe;min-height:0;">
                    <div class="font-semibold mb-2">Resumen</div>

                    
                    <div class="text-[11px] text-gray-500 uppercase tracking-wide">VENTAS</div>
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
                    <div class="flex justify-between"><span>Total contado</span><span
                            class="font-semibold">${{ number_format($resumenCaja['total_contado'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between"><span>Devoluciones (con pago)</span><span
                            class="font-semibold text-red-700">-
                            ${{ number_format($resumenCaja['devoluciones_con_pago'] ?? 0, 0, ',', '.') }}</span></div>
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

            const collectFacturaData = () => {
                const tipoFactura = document.getElementById('swal_tipo_factura').value;
                const tipoPago = document.getElementById('swal_tipo_pago').value;
                const medioPago = document.getElementById('swal_medio_pago').value;
                const obs = (document.getElementById('swal_transfer_obs').value || '').trim();
                const venc = document.getElementById('swal_fecha_venc').value;
                const recibido = parseMoney(document.getElementById('swal_monto_recibido').value);

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
                    fecha_vencimiento: tipoPago === 'credito' ? venc : null
                };
            };

            Swal.fire({
                title: 'Confirmar factura',
                width: '460px',
                html: `
                    <div style="text-align:left;width:360px;max-width:100%;margin:0 auto;font-size:13px;color:#1f2937;">
                        <div style="background:#f8fafc;border:1px solid #dbeafe;border-radius:12px;padding:10px 12px;margin-bottom:10px;">
                            <div style="font-size:11px;color:#64748b;font-weight:800;">Cliente</div>
                            <div style="font-weight:900;color:#111827;margin-bottom:7px;line-height:1.2;">${clienteVenta}</div>
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                                <span style="font-size:11px;color:#64748b;font-weight:800;">Total venta</span>
                                <b style="font-size:19px;color:#111827;white-space:nowrap;">${formatMoney(totalNumero)}</b>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                            <div>
                                <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;">Tipo de venta</label>
                                <select id="swal_tipo_factura" style="width:100%;height:36px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 9px;background:white;">
                                    <option value="salida">Salida</option>
                                    ${factusHabilitado ? '<option value="electronica">Electronica</option>' : ''}
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;">Tipo de pago</label>
                                <select id="swal_tipo_pago" style="width:100%;height:36px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 9px;background:white;">
                                    <option value="contado">Contado</option>
                                    ${creditoActivo ? '<option value="credito">Venta a credito</option>' : ''}
                                </select>
                            </div>
                        </div>

                        <div id="swal_contado_wrap">
                            <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;">Medio de pago</label>
                            <select id="swal_medio_pago" style="width:100%;height:36px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 9px;background:white;margin-bottom:8px;">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                            </select>

                            <div id="swal_efectivo_wrap" style="margin-bottom:8px;text-align:center;">
                                <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;text-align:center;">Valor recibido</label>
                                <input id="swal_monto_recibido" type="text" inputmode="numeric" value="" placeholder="0" style="display:block;width:220px;max-width:100%;height:38px;border:1px solid #cbd5e1;border-radius:10px;padding:4px 12px;text-align:center;font-weight:800;margin:0 auto;" autocomplete="off">
                                <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:8px auto 0;max-width:310px;">
                                    <button type="button" data-cash-exact="1" style="border:0;border-radius:999px;background:#2563eb;color:white;font-weight:800;font-size:11px;padding:6px 10px;">Exacto</button>
                                    <button type="button" data-cash-add="5000" style="border:0;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:6px 9px;">+5.000</button>
                                    <button type="button" data-cash-add="10000" style="border:0;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:6px 9px;">+10.000</button>
                                    <button type="button" data-cash-add="20000" style="border:0;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:6px 9px;">+20.000</button>
                                    <button type="button" data-cash-add="50000" style="border:0;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:6px 9px;">+50.000</button>
                                    <button type="button" data-cash-add="100000" style="border:0;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:800;font-size:11px;padding:6px 9px;">+100.000</button>
                                    <button type="button" data-cash-clear="1" style="border:0;border-radius:999px;background:#f3f4f6;color:#374151;font-weight:800;font-size:11px;padding:6px 10px;">Limpiar</button>
                                </div>
                                <div style="width:220px;max-width:100%;margin:7px auto 0;background:#f1f5f9;border-radius:10px;padding:7px 10px;text-align:center;">
                                    <div style="font-size:11px;font-weight:800;color:#64748b;">Vuelto</div>
                                    <b id="swal_vuelto" style="display:block;font-size:18px;color:#111827;">$0</b>
                                </div>
                            </div>

                            <div id="swal_transfer_wrap" style="display:none;margin-bottom:8px;">
                                <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;">Observacion de transferencia</label>
                                <textarea id="swal_transfer_obs" rows="2" placeholder="Banco, referencia o detalle" style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:7px 9px;resize:vertical;"></textarea>
                            </div>
                        </div>

                        <div id="swal_credito_wrap" style="display:none;">
                            <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:8px 10px;margin-bottom:8px;color:#3730a3;">
                                <div style="display:flex;justify-content:space-between;gap:8px;"><span>Cupo disponible</span><b>${formatMoney(cupoDisponible)}</b></div>
                                <div style="display:flex;justify-content:space-between;gap:8px;"><span>Deuda actual</span><b>${formatMoney(deudaCliente)}</b></div>
                                <div style="display:flex;justify-content:space-between;gap:8px;"><span>Dias de credito</span><b>${diasCredito}</b></div>
                            </div>
                            <label style="display:block;font-size:11px;font-weight:800;color:#4b5563;margin-bottom:4px;">Fecha vencimiento</label>
                            <input id="swal_fecha_venc" type="date" value="${fechaVence}" style="width:100%;height:36px;border:1px solid #cbd5e1;border-radius:9px;padding:4px 9px;">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Facturar',
                denyButtonText: 'Facturar e imprimir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4f46e5',
                denyButtonColor: '#2563eb',
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

                    const formatearMontoInput = (valor) => {
                        const numero = parseMoney(valor);
                        montoRecibido.value = numero > 0 ? formatMoney(numero).replace('$', '').trim() : '';
                        return numero;
                    };

                    const actualizarVuelto = () => {
                        const recibido = parseMoney(montoRecibido.value);
                        vuelto.textContent = formatMoney(Math.max(0, recibido - totalNumero));
                    };

                    const sync = () => {
                        const esCredito = tipoPago.value === 'credito';
                        const esTransferencia = medioPago.value === 'transferencia';
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
                preDeny: collectFacturaData
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('facturar-confirmada', { data: result.value });
                    return;
                }

                if (result.isDenied) {
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

    });
</script>
