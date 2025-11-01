<div style="display: flex; flex-direction: column; height: 91vh;">
    {{-- Encabezado: Cliente --}}
    
<div style="padding: 1rem; border-bottom: 1px solid #ddd;">
        <div class="flex items-center gap-4">
            <input type="text"
                class="flex-1 text-base bg-gray-100 border border-gray-300 rounded-full px-4 py-2 text-gray-700 font-medium cursor-not-allowed"
                value="{{ $clienteSeleccionadoNombre ?? 'Nombre Cliente' }}" disabled />

            <button wire:click="abrirModalBuscarCliente"
                class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm transition">

                Buscar Cliente
            </button>

            
    <div class="flex items-center gap-4">
        {{-- ...otros campos... --}}
         <div class="flex items-center gap-3">
      @if ($cajaEstado === 'abierta')
        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Caja abierta</span>
        <button wire:click="cerrarCajaModal" class="h-8 px-3 bg-red-600 text-white rounded text-sm">Cerrar caja</button>
      @else
        <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">Caja cerrada</span>
        <button wire:click="abrirCajaModal" class="h-8 px-3 bg-indigo-600 text-white rounded text-sm">Abrir caja</button>
      @endif
    </div>
    
</div>


        </div>
    </div>

    @if ($mostrarModalCartera)
        <div wire:key="cartera-modal-{{ $carteraRefreshKey }}"
            class="fixed inset-0 z-[60] flex items-center justify-center">
            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" wire:click="$set('mostrarModalCartera', false)"></div>

            <!-- MODAL: tamaño fijo + layout en columnas -->
            <div class="relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                style="width:1000px; max-width:95vw; height:560px;">
                <!-- Header -->
                <!-- Header -->
                <div class="px-4 py-2 border-b flex items-center justify-between shrink-0">
                    <h3 class="text-base font-semibold">Cartera / Cuentas por cobrar</h3>
                    <div class="flex items-center gap-2">
                        <button type="button"
                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition"
                            wire:click="abrirHistorial">Historial</button>

                        <button
                            class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition"
                            wire:click="$set('mostrarModalCartera', false)">Cerrar</button>
                    </div>
                </div>


                <!-- Body (ocupa todo el alto disponible) -->
                <div class="flex-1 overflow-hidden px-4 py-3">
                    <div class="flex h-full gap-4 overflow-hidden">

                        {{-- IZQUIERDA (FIJA): CLIENTES con scroll --}}
                        <div class="shrink-0 w-[380px] flex flex-col" style="height: 480px;">
                            {{-- buscador fijo arriba --}}
                            <div class="sticky top-0 z-10 bg-white pb-2">
                                <input type="text" wire:model.debounce.500ms="carteraBuscar"
                                    placeholder="Buscar cliente (nombre o identificación)"
                                    class="w-full border rounded-md px-3 py-2 text-sm">
                            </div>

                            {{-- lista con SCROLL vertical SIEMPRE visible --}}
                            <div class="flex-1 overflow-y-auto overflow-x-hidden border rounded-md divide-y pr-2 -mr-2"
                                style="min-height:0; max-height:430px;">
                                @forelse($carteraClientes as $c)
                                    <div class="p-2 hover:bg-gray-50 cursor-pointer flex items-center justify-between"
                                        wire:click="seleccionarClienteCartera({{ $c['id'] }})">
                                        <div class="min-w-0">
                                            <div class="font-medium text-sm truncate">{{ $c['nombre'] }}</div>
                                            <div class="text-xs text-gray-600">
                                                Facturas: {{ $c['facturas'] }} · Vence máx: {{ $c['max_venc'] ?? '—' }}
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

                        {{-- DERECHA: FACTURAS (scroll vertical) --}}
                        <div class="min-w-0 flex-1 flex flex-col overflow-hidden">
                            @if ($carteraClienteId)
                                <div class="flex items-center justify-between mb-2 shrink-0">
                                    <div class="text-sm text-gray-700">
                                        Total cliente: <b>${{ number_format($carteraTotalCliente, 0, ',', '.') }}</b>
                                    </div>
                                    <div class="text-xs text-gray-500">Selecciona una factura para ver o abonar.</div>
                                </div>

                                @if ($cargandoFacturas)
                                    <div class="flex-1 flex items-center justify-center text-gray-500">
                                        <span class="animate-spin mr-2">⏳</span> Cargando facturas...
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
        <th class="px-2 py-2 border border-gray-300 text-right">Devuelto</th>
        <th class="px-2 py-2 border border-gray-300 text-right">Pagado</th>
        <th class="px-2 py-2 border border-gray-300 text-right">Vence</th>
        <th class="px-2 py-2 border border-gray-300">Estado</th>
        <th class="px-2 py-2 border border-gray-300">Acciones</th>
    </tr>
</thead>
<tbody class="divide-y border-t border-gray-300" wire:key="tbody-facturas-{{ $carteraClienteId }}">
    @forelse($carteraFacturas as $f)
        <tr wire:key="row-fac-{{ $f['id'] }}">
            <td class="px-2 py-2 border border-gray-300">{{ $f['id'] }}</td>
            <td class="px-2 py-2 border border-gray-300">{{ \Carbon\Carbon::parse($f['fecha'])->format('Y-m-d') }}</td>
            <td class="px-2 py-2 text-right border border-gray-300">${{ number_format($f['total'], 0, ',', '.') }}</td>
            <td class="px-2 py-2 text-right border border-gray-300">${{ number_format($f['devuelto'], 0, ',', '.') }}</td>
            <td class="px-2 py-2 text-right border border-gray-300">${{ number_format($f['pagado'], 0, ',', '.') }}</td>
            <td class="px-2 py-2 text-right font-bold text-indigo-700 border border-gray-300">${{ number_format($f['vence'], 0, ',', '.') }}</td>
            <td class="px-2 py-2 border border-gray-300">
                <span class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-700">{{ $f['estado'] }}</span>
            </td>
            <td class="px-2 py-2 border border-gray-300">
                <div class="flex items-center gap-2">
                    <button type="button"
                        wire:key="ver-{{ $f['id'] }}"
                        wire:click.stop="verFacturaEnModal({{ $f['id'] }})"
                        class="px-2 py-1 text-xs rounded border hover:bg-gray-50 rounded-full shadow-sm transition">
                        Ver
                    </button>

                    <button type="button"
                        wire:key="abo-{{ $f['id'] }}"
                        wire:click.stop="abrirAbono({{ $f['id'] }}, {{ (int) $f['vence'] }})"
                        class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition">
                        Abonar
                    </button>

                    {{-- Pagar (saldo total) con elección de medio + observación transferencia --}}
                    <button x-data="{}"
                        x-on:click.prevent.stop="
                            const facturaId = {{ $f['id'] }};
                            const vence     = {{ (int) $f['vence'] }};
                            Swal.fire({
                              title: 'Pagar esta factura',
                              html: `
                                <div style='text-align:left;max-width:320px;margin:auto;font-size:14px'>
                                  <div style='margin-bottom:8px;color:#6b7280'>
                                    Se registrará el <b>pago total</b> por <b>$${vence.toLocaleString('es-CO')}</b>.
                                  </div>
                                  <label class='block text-xs font-semibold mb-1'>Medio de pago</label>
                                  <select id='swp_medio' class='swal2-input'
                                          style='width:100%;height:36px;padding:4px 8px;font-size:13px;margin:0 0 10px 0'>
                                    <option value='efectivo'>Efectivo</option>
                                    <option value='transferencia'>Transferencia</option>
                                    <option value='otro'>Otro</option>
                                  </select>
                                  <div id='swp_box_transfer' style='display:none'>
                                    <label class='block text-xs font-semibold mb-1'>Observación (banco / cuenta)</label>
                                    <input id='swp_transfer_obs' type='text' class='swal2-input' required
                                          style='width:100%;height:36px;padding:4px 8px;font-size:13px;margin:0'
                                          placeholder='Ej: Bancolombia - Ahorros ****1234'>
                                  </div>
                                </div>
                              `,
                              showConfirmButton: false,
                              didOpen: () => {
                                const sel     = document.getElementById('swp_medio');
                                const boxT    = document.getElementById('swp_box_transfer');
                                const inputT  = document.getElementById('swp_transfer_obs');
                                const toggle = () => {
                                  if (sel.value === 'transferencia') {
                                    boxT.style.display = 'block';
                                    inputT?.setAttribute('required', 'required');
                                    inputT?.focus();
                                  } else {
                                    boxT.style.display = 'none';
                                    inputT?.removeAttribute('required');
                                  }
                                };
                                sel.addEventListener('change', toggle);
                                toggle();
                                // Footer con botones
                                document.querySelector('.swal2-html-container').insertAdjacentHTML('beforeend', `
                                  <div style='text-align:center;margin-top:12px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;'>
                                    <button id='swp_cancel' type='button' class='swal2-cancel swal2-styled' style='font-size:13px;padding:6px 12px;height:34px'>Cancelar</button>
                                    <button id='swp_ok'     type='button' class='swal2-confirm swal2-styled' style='background:#4f46e5;font-size:13px;padding:6px 12px;height:34px'>Pagar</button>
                                  </div>
                                `);
                                document.getElementById('swp_cancel')?.addEventListener('click', () => Swal.close());
                                // Confirmar + imprimir
                                document.getElementById('swp_ok')?.addEventListener('click', () => {
                                  if (sel.value === 'transferencia') {
                                    const obs = (inputT?.value || '').trim();
                                    if (!obs) { Swal.fire('Falta observación','Escribe banco/cuenta para la transferencia.','warning'); return; }
                                  }
                                  const payload = {
                                    monto: vence,
                                    medio: sel.value,
                                    nota : 'Pago total desde Cartera',
                                    transferencia_obs: sel.value === 'transferencia'
                                      ? (inputT?.value || '').trim()
                                      : null,
                                  };
                                  // Ventana placeholder (evita bloqueador del navegador)
                                  const popup = window.open('', '_blank', 'width=400,height=600');
                                  if (popup) {
                                    popup.document.write('<!doctype html><html><head><meta charset=\'utf-8\'><title>Imprimiendo…</title></head><body style=\'font:14px sans-serif;padding:12px\'>Generando comprobante…</body></html>');
                                    popup.document.close();
                                  }
                                  Swal.close();
                                  // Método Livewire que paga e imprime
                                  $wire.pagarEImprimir(facturaId, payload)
                                    .then((r) => {
                                      r = r || {};
                                      if (r.print_url) {
                                        if (popup && !popup.closed) popup.location.replace(r.print_url);
                                        else window.open(r.print_url, '_blank');
                                        return;
                                      }
                                      if (r.html) {
                                        const w = (popup && !popup.closed) ? popup : window.open('', '_blank', 'width=400,height=600');
                                        w.document.open(); w.document.write(r.html); w.document.close(); w.focus();
                                        setTimeout(function(){ try { w.print(); } catch(e){} }, 300);
                                        return;
                                      }
                                      if (popup && !popup.closed) popup.close();
                                      Swal.fire('Error', 'No llegó contenido de impresión.', 'error');
                                    })
                                    .catch((e) => {
                                      if (popup && !popup.closed) popup.close();
                                      Swal.fire('Error', 'No se pudo registrar/imprimir el pago', 'error');
                                      console.error(e);
                                    });
                                });
                              }
                            });
                        "
                        class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition">
                        Pagar
                    </button>
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="8" class="px-2 py-8 text-center text-sm text-gray-500">
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
            class="fixed inset-0 z-[70] flex items-center justify-center">
            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarHistorial"></div>

            <div class="relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                style="width:1000px; max-width:95vw; height:560px;">
                <!-- Header -->
                <div class="px-4 py-2 border-b flex items-center justify-between">
                    <h3 class="text-base font-semibold">Historial de facturas pagadas</h3>
                    <button
                        class="px-2 py-1 text-xs rounded bg-indigo-600 text-white hover:bg-indigo-700 rounded-full shadow-sm transition"
                        wire:click="cerrarHistorial">Cerrar</button>
                </div>

                <!-- Filtros -->
                <div class="px-4 pt-2 pb-2 border-b">
                    <div class="flex items-end gap-3 flex-wrap">
                        <!-- Desde -->
                        <label class="flex flex-col gap-1 w-[170px] shrink-0">
                            <span class="text-[11px] text-gray-500">Desde</span>
                            <input type="date" class="h-8 text-sm px-2 border rounded-md"
                                wire:model.live="histDesde">
                        </label>

                        <!-- Hasta -->
                        <label class="flex flex-col gap-1 w-[170px] shrink-0">
                            <span class="text-[11px] text-gray-500">Hasta</span>
                            <input type="date" class="h-8 text-sm px-2 border rounded-md"
                                wire:model.live="histHasta">
                        </label>

                        <!-- Buscar -->
                        <label class="flex flex-col gap-1 flex-1 min-w-[240px]">
                            <span class="text-[11px] text-gray-500">Buscar (cliente o #)</span>
                            <input type="text" placeholder="Ej: Juan / 12345"
                                class="h-8 text-sm px-2 border rounded-md" wire:model.debounce.500ms="histBuscar">
                        </label>
                    </div>

                    <!-- Checkbox (puede quedar debajo; si lo quieres a la derecha, metelo al flex de arriba) -->
                    <div class="mt-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="rounded" wire:model.live="histSoloCliente">
                            <span>Solo cliente seleccionado</span>
                        </label>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="px-4 py-2 text-sm text-gray-700 flex items-center justify-between">
                    <div>
                        Registros: <b>{{ $historialCount }}</b>
                    </div>
                    <div>
                        Total pagado: <b>${{ number_format($historialTotal, 0, ',', '.') }}</b>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="flex-1 overflow-y-auto overflow-x-hidden px-4 pb-4">
                    <table class="w-full text-sm border border-gray-300">
                        <thead class="bg-gray-100 border-b border-gray-300">
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
                                    <td class="px-2 py-2 border border-gray-300">{{ $h['id'] }}</td>
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
                                            class="px-2 py-1 text-xs rounded border hover:bg-gray-50 rounded-full shadow-sm transition"
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


     {{-- MODAL: Ver Factura (solo lectura, encima de Cartera) --}}
    @if ($mostrarModalFactura && $verFacturaId)
        <div class="fixed inset-0 z-[70] flex items-center justify-center" wire:key="modal-fac-{{ $verFacturaId }}">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarFacturaModal"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-[480px] max-w-[95vw] h-[78vh] overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 border-b">
                    <h4 class="text-sm font-semibold">Factura #{{ $verFacturaId }} (solo lectura)</h4>
                    <button class="text-gray-500 hover:text-gray-700" wire:click="cerrarFacturaModal">✕</button>
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
<div class="fixed inset-0 z-[75] flex items-center justify-center">
  <div class="absolute inset-0 bg-black/45 backdrop-blur-[1px]" wire:click="cerrarAbono"></div>

  <div
    class="relative w-[540px] max-w-[94vw] overflow-hidden rounded-2xl shadow-2xl ring-1 ring-black/5 bg-white"
    x-data="{
      saldo: {{ (int) $abonoSaldo }},
      amount: Number({{ (int) $abonoMonto }}) || 0,
      medio: @entangle('abonoMedio').live,
      fmt(n){ return new Intl.NumberFormat('es-CO').format(Math.max(0, Math.round(n))) }
    }"
  >
    {{-- Header con más padding --}}
    <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
      <div class="min-w-0">
        <div class="text-sm font-semibold text-gray-800">Abonar a factura</div>
        <div class="text-xs text-gray-500 mt-0.5">Factura #{{ $abonoFacturaId }}</div>
      </div>
      <button class="text-gray-500 hover:text-gray-700" wire:click="cerrarAbono" aria-label="Cerrar">✕</button>
    </div>

    {{-- Body con buen margen interno y separación vertical --}}
    <div class="px-6 py-6 space-y-6">

      {{-- Fila: saldo actual --}}
      <div class="flex items-center justify-between">
        <span class="text-xs text-gray-500">Saldo actual</span>
        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
          $ {{ number_format($abonoVence, 0, ',', '.') }}
        </span>
      </div>

      {{-- Monto --}}
      <div class="space-y-2">
        <label class="text-xs font-semibold text-gray-700">Monto a abonar</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">$</span>
          <input
            id="inp_abono_monto"
            type="number" min="0" max="{{ (int) $abonoVence }}" step="1"
            class="w-full border rounded-2xl pl-7 pr-3 h-11 text-sm text-right focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
            wire:model.live="abonoMonto"
          />
        </div>
        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full bg-indigo-500 transition-[width] duration-300"
               :style="{ width: (saldo ? Math.min(100, (amount / saldo) * 100) : 0) + '%' }"></div>
        </div>
      </div>

      {{-- Medio + Obs transferencia (bloques con espacio) --}}
      <div class="space-y-4" x-data="{ medio: @entangle('abonoMedio').live }">
        <div class="space-y-2">
          <label class="text-xs font-semibold text-gray-700">Medio</label>
          <select
            class="w-full border rounded-2xl px-3 h-11 text-sm focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
            x-model="medio" wire:model.live="abonoMedio"
            @change="
              if (medio === 'transferencia') { $refs.obs?.setAttribute('required','required'); $refs.obs?.focus(); }
              else { $refs.obs?.removeAttribute('required'); }
            "
          >
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="otro">Otro</option>
          </select>
        </div>

        <div class="space-y-2" x-show="medio === 'transferencia'" x-cloak>
          <label class="text-xs font-semibold text-gray-700">Observación (banco / cuenta)</label>
          <input
            x-ref="obs" id="abono_transfer_obs" type="text"
            class="w-full border rounded-2xl px-3 h-11 text-sm focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500"
            wire:model.lazy="abonoTransferObs"
            placeholder="Ej: Bancolombia - Ahorros ****1234"
            :required="medio === 'transferencia'"
          />
          @error('abonoTransferObs')
            <div class="text-red-600 text-xs">{{ $message }}</div>
          @enderror
        </div>
      </div>

      {{-- Botones alineados y con respiro --}}
      <div class="flex justify-end gap-3 pt-2">
        <button type="button"
          class="h-10 px-4 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition"
          @click.prevent="$wire.cerrarAbono()">
          Cancelar
        </button>

        <button type="button"
          class="h-10 px-5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition"
          @click.prevent="
            const elMonto  = document.getElementById('inp_abono_monto');
            let m = (elMonto && !Number.isNaN(elMonto.valueAsNumber)) ? Math.trunc(elMonto.valueAsNumber) : 0;
            const maxSaldo = {{ (int) $abonoSaldo }};
            if (m < 0) m = 0; if (m > maxSaldo) m = maxSaldo;

            if (medio === 'transferencia') {
              const obsEl = $refs.obs; const obsVal = (obsEl?.value || '').trim();
              if (!obsVal) { obsEl?.focus(); Swal.fire('Falta observación','Escribe banco/cuenta para la transferencia.','warning'); return; }
            }

            Swal.fire({
              title: 'Confirmar abono',
              html: 'Se abonarán <b>$ ' + m.toLocaleString('es-CO') + '</b> (' + medio + ').',
              icon: 'question', showCancelButton: true,
              confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', reverseButtons: true
            }).then(function(res){ if (res.isConfirmed) { $wire.confirmarAbonoConValor(m); }});
          "
          wire:loading.attr="disabled">
          Guardar
        </button>
      </div>
    </div>
  </div>
</div>
@endif



    {{-- TABLA ULTRA COMPACTA --}}
    <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-1 py-1"
        style="font-size: 12px; line-height: 1.1;">
        <table class="w-full table-fixed bg-white border">
            <thead class="sticky top-0 z-20 bg-gray-100 text-gray-700">
                <tr class="border-b">
                    <th class="px-1 py-1 text-left w-14">Código</th>
                    <th class="px-1 py-1 text-left">Producto</th>
                    <th class="px-1 py-1 text-center w-10">Cant.</th>
                    <th class="px-1 py-1 text-center w-14">Precio</th>
                    <th class="px-1 py-1 text-center w-16">Subtotal</th>
                    <th class="px-1 py-1 text-center w-10">Acción</th>
                </tr>
            </thead>

            <tbody>
                @forelse($carrito as $id => $item)
                    <tr class="border-b hover:bg-indigo-50">
                        <td class="px-1 py-0.5 border">{{ $item['id_producto'] ?? '-' }}</td>
                        <td class="px-1 py-0.5 border truncate {{ ($item['existencias'] ?? 0) <= 0 ? 'text-red-600 font-semibold' : '' }}"
                            title="Existencias: {{ $item['existencias'] ?? 0 }}">
                            {{ $item['nombre'] }}
                        </td>
                        <td class="px-1 py-0.5 border text-center">
                            <input type="number" min="1"
                                wire:model.lazy="carrito.{{ $id }}.cantidad"
                                wire:change="actualizarTotales"
                                wire:key="cantidad-{{ $id }}-{{ $item['cantidad'] }}"
                                class="border rounded text-center"
                                style="width: 30px; height: 18px; font-size: 10px; padding: 0;" />
                        </td>
                        <td class="px-1 py-0.5 border text-center whitespace-nowrap">
                            ${{ number_format(round($item['nuevo_precio'] ?? ($item['precio'] ?? 0)), 0, ',', '.') }}
                        </td>
                        <td class="px-1 py-0.5 border text-center font-semibold text-indigo-600 whitespace-nowrap">
                            ${{ number_format($item['total'] ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-1 py-0.5 border text-center">
                            <div class="flex items-center justify-center gap-[1px]">
                                <button x-data
                                    x-on:click="$wire.abrirModalRenombrar('{{ $item['uuid'] ?? $item['id_producto'] }}')"
                                    class="text-blue-600 hover:text-blue-800" style="font-size: 10px;"
                                    title="Editar">✎</button>
                                <button x-data="{ uuid: @js($item['uuid'] ?? $item['id_producto']) }"
                                    x-on:click="Swal.fire({ title:'¿Borrar?', icon:'warning', showCancelButton:true, confirmButtonText:'Sí', cancelButtonText:'No' }).then(r=>{ if(r.isConfirmed){ $wire.eliminarDelCarrito(uuid) }})"
                                    class="text-red-600 hover:text-red-800" style="font-size: 10px;"
                                    title="Eliminar">×</button>
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



{{-- Pie: compacto + total grande + Facturar a la izquierda --}}

    <div class="px-3 py-2 border-t bg-white flex items-center justify-between">
        <script>
  window.__debugCredito = @json($creditoInfo ?? null);
  console.log('DEBUG credito desde Blade:', window.__debugCredito);
</script>

        <button type="button" id="btn-abrir-facturar"
            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
            onclick="uiAbrirFacturar()">
            🧾 Facturar
        </button>

        <div class="text-right">
            <span class="text-indigo-700 font-bold text-sm align-middle">TOTAL:</span>
            <span class="text-gray-900 font-extrabold text-2xl sm:text-3xl align-middle">
                ${{ number_format($totalGeneral ?? 0, 0, ',', '.') }}
            </span>
        </div>
    </div>



{{-- Observaciones: pequeño + auto-resize (crece solo si hace falta) --}}
    <div class="mb-2 w-full">
        <textarea
            class="form-control w-full rounded border border-gray-300 px-3 py-1 resize-y min-h-[36px] focus:outline-none focus:ring-2 focus:ring-indigo-500"
            id="identificadorPrefactura" wire:model="observacionesPrefactura"
            wire:key="observacion-input-{{ $observacionKey }}" placeholder="Ingrese una observacion para esta factura"
            rows="1"></textarea>

    </div>


{{-- Botonera: compacta --}}
    <div class="flex items-center justify-between">
        <button
            x-on:click="
                        if (($wire.get('carrito') ?? []).length === 0) {
                          Swal.fire({icon:'warning', title:'Carrito vacío', text:'Debe agregar productos antes de editar.'});
                        } else { $wire.abrirModalEditar(); }
                      "
            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
            ✏️ Editar
        </button>

        <div class="flex gap-2">

            <button wire:click="abrirModalCrearCliente"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                + Crear Cliente
            </button>

            <button type="button"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow"
                wire:click="abrirModalCartera">
                💼 Cartera
            </button>
            <button
                x-on:click="Swal.fire({
                          title:'¿Vaciar carrito?', text:'Se eliminarán todos los productos.',
                          icon:'warning', showCancelButton:true, confirmButtonText:'Sí, vaciar', cancelButtonText:'Cancelar'
                        }).then(r=>{ if(r.isConfirmed){ $wire.limpiarCarrito(); }})"
                class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 h-8 rounded-full">
                🗑️ Limpiar
            </button>

            <button
                x-on:click="
                          if (($wire.get('carrito') ?? []).length === 0) {
                            Swal.fire({icon:'warning', title:'Carrito vacío', text:'Debe agregar productos antes de guardar.'});
                          } else {
                            Swal.fire({
                              title:'¿Guardar prefactura?', icon:'question', showCancelButton:true,
                              confirmButtonText:'Sí, guardar', cancelButtonText:'Cancelar'
                            }).then(r=>{ if(r.isConfirmed){ $wire.guardarPrefacturaConfirmada(); }});
                          }
                        "
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                💾 Guardar
            </button>

            <button wire:click="verPrefacturas"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 h-8 rounded-full shadow">
                📂 Ver
            </button>
        </div>
    </div>

@if ($mostrarModalCrearCliente)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-blue-100">
        <div class="bg-white p-6 rounded-xl shadow-xl" style="width: 90%; max-width: 600px;">
            <h2 class="text-xl text-sm font-semibold text-indigo-700 text-center mb-4">Crear Cliente</h2>


            {{-- DATOS PERSONALES --}}
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
                        <label class="text-sm block mb-1">Número de documento</label>
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
                    <label class="text-sm block mb-1">Razón Social</label>
                    <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                        wire:model.defer="nuevoCliente.razon_social">
                    @error('nuevoCliente.razon_social')
                        <span class="text-red-500 text-xs">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- DATOS DE CONTACTO --}}
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-indigo-700 mb-2">Datos de contacto</h3>
                <div class="mb-2" style="width: 100%;">
                    <label class="text-sm block mb-1">Correo electrónico</label>
                    <input type="email" class="border rounded px-2 py-1 w-full text-sm"
                        wire:model.defer="nuevoCliente.email">
                    @error('nuevoCliente.email')
                        <span class="text-red-500 text-xs">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex gap-4">
                    <div style="width: 50%;">
                        <label class="text-sm">Teléfono</label>
                        <input type="tel" pattern="[0-9]*" inputmode="numeric"
                            class="border rounded px-2 py-1 w-full text-sm" wire:model.defer="nuevoCliente.telefono"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        @error('nuevoCliente.telefono')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                    <div style="width: 50%;">
                        <label class="text-sm">Dirección</label>
                        <input type="text" class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.direccion">
                        @error('nuevoCliente.direccion')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- UBICACIÓN --}}
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-indigo-700 mb-2">Ubicación</h3>
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

            {{-- DATOS TRIBUTARIOS --}}
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-indigo-700 mb-2">Datos tributarios</h3>
                <div class="flex gap-4">
                    <div style="width: 33.3%;">
                        <label class="text-sm">Tipo de persona</label>
                        <select class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.tipo_persona">
                            <option value="">Seleccione</option>
                            <option value="natural">Natural</option>
                            <option value="juridica">Jurídica</option>
                        </select>
                        @error('nuevoCliente.tipo_persona')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                    <div style="width: 33.3%;">
                        <label class="text-sm">Régimen tributario</label>
                        <select class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.regimen_tributario">
                            <option value="">Seleccione</option>
                            <option value="comun">Común</option>
                            <option value="simplificado">Simplificado</option>
                            <option value="especial">Especial</option>
                            <option value="otro">Otro</option>
                        </select>
                        @error('nuevoCliente.regimen_tributario')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                    <div style="width: 33.3%;">
                        <label class="text-sm">¿Responsable de IVA?</label>
                        <select class="border rounded px-2 py-1 w-full text-sm"
                            wire:model.defer="nuevoCliente.responsable_iva">
                            <option value="">Seleccione</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                        @error('nuevoCliente.responsable_iva')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- BOTONES --}}
            <div class="flex justify-start gap-2 mt-6">
                <button x-data
                    x-on:click="
                        Swal.fire({
                            title: '¿Crear cliente?',
                            text: '¿Deseas guardar este nuevo cliente?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#38a169',
                            cancelButtonColor: '#e53e3e',
                            confirmButtonText: 'Sí, guardar',
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
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
@endif

@if ($mostrarModalClientes)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-4" style="width: 600px; max-height: 80vh;">
            <table class="w-full">
                <tr>
                    <td colspan="2" class="text-center text-lg font-bold pb-3">
                        Seleccionar Cliente
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="pb-3">
                        {{-- Campo de búsqueda --}}
                        <input type="text" wire:model.live="buscarCliente"
                            placeholder="Buscar por nombre o cédula..."
                            class="w-full px-3 py-2 border rounded text-sm" />
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="overflow-y-auto border rounded" style="max-height: 300px;">
                            <table class="w-full text-sm">
                                @forelse ($clientes as $cliente)
                                    <tr wire:key="cliente-{{ $cliente->id }}"
                                        wire:click="seleccionarCliente({{ $cliente->id_clip_pro }})"
                                        class="hover:bg-blue-100 cursor-pointer border-b">
                                        <td class="px-3 py-2">
                                            <div class="font-semibold">{{ $cliente->nombre }}</div>
                                            <div class="text-xs text-gray-500">N° {{ $cliente->identificacion }}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-2 text-center text-gray-500">
                                            Cliente no existe
                                        </td>
                                    </tr>
                                @endforelse
                            </table>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="pt-4 text-center">
                        <button wire:click="$set('mostrarModalClientes', false)"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-full shadow-sm">
                            Cerrar
                        </button>
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endif

{{-- ...existing code hasta el modal de editar... --}}

@if ($mostrarModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-blue-100">
        <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-lg flex flex-col"
            style="max-height: 90vh; height: 90vh; overflow-y: auto;" x-data="{
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
            }" x-init="recalcularTotal()
            setTimeout(() => { totalOriginal = total }, 20);"
            @recalcular-total.window="recalcularTotal()">
            <h2 class="text-2xl font-bold text-blue-800 mb-6">Editar Detalles de Productos</h2>
            <div class="rounded border bg-blue-100 p-2 flex-1" style="max-height: 400px; overflow-y: auto;">
                <table class="min-w-full text-xs bg-blue-100">
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
                    <thead>
                        <tr class="text-blue-700 border-b font-bold text-sm bg-blue-200">
                            <th class="px-2 p-2 border py-2 text-center">Código</th>
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
                            <tr data-carrito-item wire:key="carrito-{{ $id }}"
                                :key="'carrito-' + {{ $id }}" x-data="{
                                    // ✅ USAR LOS VALORES ACTUALES DEL CARRITO, NO LOS ORIGINALES
                                    precioReal: {{ floatval($preciosBase[$id] ?? ($item['precio'] ?? 0)) }},
                                    descuento: {{ floatval($item['descuento'] ?? 0) }},
                                    nuevoPrecio: {{ floatval($item['nuevo_precio'] ?? ($item['precio'] ?? 0)) }},
                                
                                    descuentoManual: false,
                                    costo: {{ floatval($item['costo'] ?? 0) }},
                                    iva: {{ floatval($item['iva_venta'] ?? 0) }},
                                    cantidad: {{ floatval($item['cantidad'] ?? 1) }},
                                
                                    get costoMasIva() {
                                        return this.costo + (this.costo * this.iva / 100);
                                    },
                                
                                    // ✅ INICIALIZAR CORRECTAMENTE AL ABRIR EL MODAL
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
                                            // Recalcula el descuento basado en el mínimo permitido
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
                                        if (costoConIva <= 0) return '0.00';
                                        return ((this.nuevoPrecio - costoConIva) / costoConIva * 100).toFixed(2);
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
                                    {{ isset($item['utilidad1']) ? number_format($item['utilidad1'], 2, ',', '.') . '%' : '0,00%' }}
                                </td>
                                <td class="px-2 p-2 border py-2 text-center" data-cantidad>
                                    {{ $item['cantidad'] ?? 1 }}</td>
                                <td class="px-2 p-2 border py-2 text-center">
                                    ${{ number_format($item['costo'] ?? 0, 0, ',', '.') }}
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
            <div class="w-full text-right text-lg font-bold text-blue-800 mt-3 mb-2 pr-2">
                TOTAL: $<span x-text="total.toLocaleString('es-CO')"></span>
            </div>
            <template x-if="total !== totalOriginal">
                <div class="w-full text-right mt-1">
                    <span :class="total > totalOriginal ? 'text-green-600' : 'text-red-600'" class="font-semibold"
                        x-text="(total > totalOriginal ? 'Aumentó: +' : 'Descontó: -') + Math.abs(total - totalOriginal).toLocaleString('es-CO')"></span>
                </div>
            </template>
            <!-- Botones al pie del modal -->
            <div class="flex justify-end gap-4 pt-2">
                <button x-data
                    x-on:click="
                        const cambios = {};
                        document.querySelectorAll('tr[data-carrito-item]').forEach(tr => {
                            const key = tr.getAttribute('wire:key').replace('carrito-', '');
                            const nuevo = tr.querySelector('input[x-ref^=nuevoPrecio]')?.value;
                            const desc = tr.querySelector('input[x-ref^=descuento]')?.value;
                            
                            if (key) {
                                cambios[key] = {
                                    nuevo_precio: parseInt(nuevo) || 0,
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
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 text-sm rounded-full shadow transition font-bold"
                    title="Cancelar">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
@endif

@if ($mostrarModalPrefacturas)
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        {{-- Fondo --}}
        <div class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm"></div>

        {{-- Modal --}}
        <div class="relative bg-white rounded-xl shadow-xl border border-gray-400 flex flex-col overflow-hidden"
            style="width:80vw;height:90vh;">

            {{-- Header + Tabs --}}
            <div class="px-6 py-3 border-b bg-gray-100 text-gray-800">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold">Gestión de ventas</span>
                    <div class="ml-auto flex gap-2">
                        <button wire:click="setTab('prefacturas')"
                            class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $tab === 'prefacturas' ? 'bg-indigo-600 text-white' : 'bg-white border' }}">
                            Prefacturas
                        </button>
                        <button wire:click="setTab('facturas')"
                            class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $tab === 'facturas' ? 'bg-indigo-600 text-white' : 'bg-white border' }}">
                            Facturas
                        </button>

                    </div>
                </div>
            </div>

            {{-- Filtros (solo Facturas) --}}
            @if ($tab === 'facturas')
                <div class="px-6 py-2 border-b bg-white text-sm flex items-end gap-3">
                    <label class="text-xs text-gray-600 flex flex-col">
                        <span>Desde</span>
                        <input type="date" wire:model.live.debounce.300ms="fDesde" min="{{ $minFechaFacturas }}"
                            max="{{ $maxFechaFacturas }}" class="border rounded-md px-2 h-[38px] leading-none" />
                    </label>

                    <label class="text-xs text-gray-600 flex flex-col">
                        <span>Hasta</span>
                        <input type="date" wire:model.live.debounce.300ms="fHasta" min="{{ $minFechaFacturas }}"
                            max="{{ $maxFechaFacturas }}" class="border rounded-md px-2 h-[38px] leading-none" />
                    </label>

                    <button type="button" wire:click="actualizarRangoFacturas"
                        class="inline-flex items-center justify-center h-[38px] px-4 rounded-md bg-gray-800 text-white hover:bg-gray-900 leading-none">
                        Filtrar
                    </button>


                </div>
            @endif

            {{-- Cuerpo en dos columnas --}}
            <div class="flex flex-1 overflow-hidden text-sm text-gray-800">

                {{-- Panel izquierdo --}}
                <div class="w-1/2 border-r overflow-y-auto p-3 bg-white">
                    @if ($tab === 'prefacturas')

                        <table class="w-full table-fixed border-collapse">
                            <thead class="bg-indigo-100 sticky top-0 z-10">
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
                                        $total = '$' . number_format($pf->productos->sum('subtotal'), 0, ',', '.');
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
                        {{-- FACTURAS agrupadas por día --}}
                        @php
                            $agrupadas = collect($facturas)->groupBy(
                                fn($f) => \Carbon\Carbon::parse($f->fecha)->format('Y-m-d'),
                            );
                        @endphp

                        @forelse ($agrupadas as $dia => $items)
                            <div class="font-bold text-indigo-700 mt-2 mb-1">{{ $dia }}</div>
                            <table class="w-full table-fixed border-collapse mb-3">
                                <thead class="bg-indigo-100 sticky top-0 z-10">
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
                                            <td class="p-2 border">{{ $f->id }}</td>
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

                {{-- Panel derecho --}}
                <div class="w-1/2 flex flex-col h-full"
                    @if ($tab === 'prefacturas') wire:key="panel-prefactura-{{ $prefacturaSeleccionada->id ?? 'none' }}" @endif>

                    {{-- ======= PREFacturas: detalle ======= --}}
                    @if ($tab === 'prefacturas')

                        @if ($prefacturaSeleccionada)
                            <h3 class="text-base font-semibold text-gray-800 mb-2">Detalles Prefactura</h3>

                            <div class="flex-1 overflow-y-auto border rounded-lg text-sm">
                                <table class="w-full text-xs">
                                    <thead class="sticky top-0 bg-indigo-100 text-indigo-800 font-semibold z-10">
                                        <tr>
                                            <th class="p-2 border">Código</th>
                                            <th class="p-2 border">Descripción</th>
                                            <th class="p-2 border text-center">Cant</th>
                                            <th class="p-2 border text-right">Precio</th>
                                            <th class="p-2 border text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detalleSeleccionado as $item)
                                            <tr class="even:bg-gray-50 hover:bg-indigo-50 transition">
                                                <td class="p-2 border text-center">{{ $item['producto_id'] }}</td>
                                                <td class="p-2 border">{{ $item['descripcion_larga'] }}</td>
                                                <td class="p-2 border text-center">{{ $item['cantidad'] }}</td>
                                                <td class="p-2 border text-right">
                                                    ${{ number_format($item['precio_unitario'], 0, ',', '.') }}</td>
                                                <td class="p-2 border text-right">
                                                    ${{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-2 px-1">
                                <div class="flex justify-between text-sm text-gray-800 font-semibold border-t pt-2">
                                    <span>Total ítems: {{ count($detalleSeleccionado) }}</span>
                                    <span>Total:
                                        ${{ number_format(collect($detalleSeleccionado)->sum('subtotal'), 0, ',', '.') }}</span>
                                </div>

                                <div class="mt-2 text-sm">
                                    <label class="font-semibold text-gray-700 block mb-1">Observaciones:</label>
                                    <div class="bg-gray-100 p-2 rounded text-gray-800">
                                        {{ $observacionesPrefactura }}
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-500 flex-1 flex items-center justify-center text-center">
                                Selecciona una prefactura para ver los detalles.
                            </div>
                        @endif

                        {{-- ======= FACTURAS: detalle + barra acciones siempre ======= --}}
                    @else
                        @if ($facturaSeleccionada)
                            <h3 class="text-base font-semibold text-gray-800 mb-2">
                                Detalles de Factura
                            </h3>

                            <div class="flex-1 overflow-y-auto border rounded-lg text-sm">
                                <table class="w-full text-xs">
                                    <thead class="sticky top-0 bg-indigo-100 text-indigo-800 font-semibold z-10">
                                        <tr>
                                            <th class="p-2 border">Código</th>
                                            <th class="p-2 border">Descripción</th>
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
                                                <td class="p-2 border text-center">{{ $d['producto_id'] }}</td>
                                                <td class="p-2 border">{{ $d['descripcion_larga'] }}</td>
                                                <td class="p-2 border text-center">{{ $d['cantidad'] }}</td>
                                                <td class="p-2 border text-center text-blue-700 font-semibold">
                                                    {{ $d['devuelto_cantidad'] }}</td>
                                                <td class="p-2 border text-center text-amber-700 font-semibold">
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
                                <div class="flex justify-between text-sm text-gray-800 font-semibold border-t pt-2">
                                    <span>Total ítems: {{ count($detalleFacturaSeleccionada) }}</span>
                                    <span>Total: ${{ number_format($facturaSeleccionada->total, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="mt-3 px-1 text-sm">
                                <label class="font-semibold text-gray-700 block mb-1">Observaciones:</label>
                                <div class="bg-gray-100 p-2 rounded text-gray-800 whitespace-pre-line min-h-[38px]">
                                    {{ $facturaSeleccionada->observaciones ?: '—' }}
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-500 flex-1 flex items-center justify-center text-center">
                                Selecciona una factura para ver los detalles.
                            </div>
                        @endif

                        {{-- Barra de acciones SIEMPRE visible en Facturas --}}
                        <div class="p-3 flex justify-end gap-2 border-t mt-2">
                            @if ($facturaSeleccionada)
                                <button
                                    onclick="window.open('{{ route('factura.imprimir', $facturaSeleccionada->id) }}','_blank','width=400,height=600')"
                                    class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                                    🖨️ Imprimir
                                </button>

                                <button x-data
                                    x-on:click="
                    (async () => {
                      if (!window.Swal) { alert('SweetAlert no está cargado'); return; }

                      // 1) Elegir tipo (completa/parcial)
                      const res = await Swal.fire({
                        title: 'Devolución',
                        input: 'select',
                        inputOptions: { completa: 'Completa', parcial: 'Parcial' },
                        inputValue: 'completa',
                        showCancelButton: true,
                        confirmButtonText: 'Continuar',
                        cancelButtonText: 'Cancelar',
                      });
                      if (!res.isConfirmed) return;

                      // 2) Pedir al backend que prepare el carrito de devolución y muestre el modal
                      await $wire.prepararDevolucion(res.value);
                    })()
                  "
                                    class="h-9 px-4 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-full"
    @if($facturaSeleccionada->tipo_pago === 'credito' && $facturaSeleccionada->estado_pago !== 'pagada') disabled @endif
>
    🔁 Devolución
</button>
                            @endif

                            {{-- Cerrar SIEMPRE visible --}}
                            <button wire:click="$set('mostrarModalPrefacturas', false)"
                                class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                                Cerrar
                            </button>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Footer SOLO en Prefacturas --}}
            @if ($tab === 'prefacturas')
                <div class="p-4 border-t flex justify-end gap-3 bg-white">
                    @if ($prefacturaSeleccionada)
                        <button
                            onclick="window.open('{{ route('prefactura.imprimir', $prefacturaSeleccionada->id) }}','_blank','width=400,height=600')"
                            class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                            🖨️ Imprimir
                        </button>

                        <button x-data
                            x-on:click="
                                    Swal.fire({
                                      title: '¿Borrar prefactura?',
                                      text: '⚠️ Esta acción eliminará la prefactura seleccionada. ¿Estás seguro?',
                                      icon: 'warning',
                                      showCancelButton: true,
                                      confirmButtonColor: '#d33',
                                      cancelButtonColor: '#3085d6',
                                      confirmButtonText: 'Sí, borrar',
                                      cancelButtonText: 'Cancelar',
                                      reverseButtons: true
                                    }).then((result)=>{ if(result.isConfirmed){ $wire.borrarPrefacturaConfirmada().then(()=>{ Swal.fire({title:'Prefactura eliminada',icon:'success',timer:1500,showConfirmButton:false}); }); } });"
                            class="h-9 px-4 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-full">
                            🗑️ Borrar
                        </button>

                        @php $hayProductosEnCarrito = count($carrito) > 0; @endphp
                        <button x-data="{ hayProductos: {{ $hayProductosEnCarrito ? 'true' : 'false' }} }"
                            x-on:click="
                                    if (hayProductos) {
                                      Swal.fire({icon:'warning',title:'Carrito con productos',text:'Limpie el carrito antes de cargar otra prefactura.',confirmButtonText:'Entendido'});
                                    } else {
                                      $wire.cargarPrefacturaAlCarrito({{ $prefacturaSeleccionada->id }});
                                    }"
                            wire:key="prefactura-btn-{{ $prefacturaSeleccionada->id }}" wire:loading.attr="disabled"
                            wire:target="cargarPrefacturaAlCarrito"
                            class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                            🛒 Cargar
                        </button>
                    @endif

                    <button wire:click="$set('mostrarModalPrefacturas', false)"
                        class="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-full shadow-sm">
                        Cerrar
                    </button>
                </div>
            @endif

        </div>
    </div>
@endif


@if ($mostrarModalDevolucion)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-[920px] max-h-[85vh] overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <div class="font-bold text-lg">Devolución @if ($tipoDevolucion === 'completa')
                        (Completa)
                    @else
                        (Parcial)
                    @endif
                </div>
                <button class="text-gray-500 hover:text-gray-800"
                    wire:click="$set('mostrarModalDevolucion', false)">✕</button>
            </div>

            <div class="p-4">
                <div class="flex items-center gap-2 mb-3">
                    <button wire:click="seleccionarTodosDevolucion"
                        class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-sm">Seleccionar todos</button>
                    <button wire:click="limpiarSeleccionDevolucion"
                        class="px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-sm">Limpiar</button>
                    <div class="ml-auto text-sm text-gray-600">
                        Cliente: <span
                            class="font-semibold">{{ $facturaSeleccionada?->cliente?->nombre ?? 'N/A' }}</span>
                    </div>
                </div>

                <div class="border rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Código</th>
                                <th class="px-3 py-2 text-left">Descripción</th>
                                <th class="px-3 py-2 text-right">Pendiente</th>
                                <th class="px-3 py-2 text-right">Precio</th>
                                <th class="px-3 py-2 text-right">Cantidad</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($carritoDevolucion as $detId => $row)
                                <tr class="border-t">
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
                                    <td colspan="7" class="px-3 py-6 text-center text-gray-500">No hay ítems
                                        pendientes para devolver.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-end gap-4">
                    <div class="text-sm text-gray-600">Total devolución:</div>
                    <div class="text-xl font-extrabold">${{ number_format($totalDevolucion, 2) }}</div>
                </div>
            </div>

            <div class="px-5 py-3 border-t flex items-center justify-end gap-3">
                <button class="h-9 px-4 rounded-full bg-gray-200 hover:bg-gray-300"
                    wire:click="$set('mostrarModalDevolucion', false)">Cancelar</button>
                <button class="h-9 px-4 rounded-full bg-red-600 hover:bg-red-700 text-white"
                    wire:click="confirmarDevolucion" @disabled(count(array_filter($carritoDevolucion, fn($r) => $r['seleccion'] && $r['cantidad'] > 0)) === 0)>
                    Devolver
                </button>
            </div>
        </div>
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


@if($mostrarModalAbrirCaja)
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 p-6 w-full"
       style="max-width:360px; text-align:center; font-size:14px">
    <h2 class="text-xl font-bold mb-4">Abrir Caja</h2>

    <div style="width:100%;max-width:280px;margin:0 auto;text-align:left">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">
        Monto de apertura
      </label>
      <input type="number"
             wire:model.defer="montoApertura"
             class="w-full border rounded px-3 h-9"
             min="0" step="1" placeholder="0">
      @error('montoApertura')
        <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
      @enderror
    </div>

    <div class="mt-5 flex justify-center gap-2">
  <button class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded"
          wire:click="$set('mostrarModalAbrirCaja', false)">Cancelar</button>
  <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded"
          wire:click="confirmarAbrirCaja">Abrir</button>
</div>

  </div>
</div>
@endif


@if($mostrarModalCerrarCaja)
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 p-6 w-full"
     style="max-width:360px; text-align:center; font-size:14px"
     x-data="{
        // 👇 TOTAL contado del día (efectivo + transferencia)
        contadoDia: {{ (int) ($resumenCaja['total_contado'] ?? 0) }},
        // enlazado al input 'Monto de cierre'
        monto: @entangle('montoCierre').live,
        // diferencia para el cuadre
        get dif(){ return Number(this.monto || 0) - Number(this.contadoDia || 0) },
        get abs(){ return Math.abs(this.dif) },
        fmt(n){ return '$' + Number(n||0).toLocaleString('es-CO') },
        get estado(){
          if (this.dif > 0) return {txt:'Sobró',  cls:'text-green-600'};
          if (this.dif < 0) return {txt:'Faltó',  cls:'text-red-600'};
          return               {txt:'Cuadre perfecto:', cls:'text-gray-700'};
        }
     }">

    <h2 class="text-xl font-bold mb-4">Cerrar Caja</h2>

    <div class="mb-4 bg-gray-50 rounded-lg p-3 border text-sm" style="text-align:left">
  <div class="font-semibold mb-2">Resumen</div>

  {{-- VENTAS --}}
  <div class="text-[11px] text-gray-500 uppercase tracking-wide">VENTAS</div>
  <div class="flex justify-between"><span>Contado · Efectivo</span><span class="font-semibold">${{ number_format($resumenCaja['ventas_contado_efectivo'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Contado · Transferencia</span><span class="font-semibold">${{ number_format($resumenCaja['ventas_contado_transferencia'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Crédito</span><span class="font-semibold">${{ number_format($resumenCaja['ventas_credito'], 0, ',', '.') }}</span></div>

  {{-- CARTERA (cobros) --}}
  <div class="mt-2 text-[11px] text-gray-500 uppercase tracking-wide">CARTERA</div>
  <div class="flex justify-between"><span>Efectivo</span><span class="font-semibold">${{ number_format($resumenCaja['cartera_efectivo'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Transferencia</span><span class="font-semibold">${{ number_format($resumenCaja['cartera_transferencia'], 0, ',', '.') }}</span></div>

  <div class="my-2 border-t"></div>

  {{-- FLUJO DEL DÍA (para cuadre) --}}
  <div class="flex justify-between"><span>Efectivo del día</span><span class="font-semibold text-green-700">${{ number_format($resumenCaja['efectivo'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Transferencia del día</span><span class="font-semibold text-blue-700">${{ number_format($resumenCaja['transferencia'], 0, ',', '.') }}</span></div>

  <div class="my-2 border-t"></div>

  {{-- TOTALES --}}
  <div class="flex justify-between"><span>Total ventas</span><span class="font-semibold">${{ number_format($resumenCaja['total_ventas'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Total contado</span><span class="font-semibold">${{ number_format($resumenCaja['total_contado'], 0, ',', '.') }}</span></div>
  <div class="flex justify-between"><span>Devoluciones (con pago)</span><span class="font-semibold text-red-700">- ${{ number_format($resumenCaja['devoluciones_con_pago'] ?? 0, 0, ',', '.') }}</span></div>
</div>


    <div style="width:100%;max-width:280px;margin:0 auto;text-align:left">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Monto de cierre</label>
      <input type="number" min="0" step="1"
             class="w-full border rounded px-3 h-9"
             x-model.number="monto" placeholder="0">
    </div>

    <div class="mt-4 text-lg font-bold" x-effect="$wire.set('diferenciaCaja', dif)">
  <span :class="estado.cls"
        x-text="dif === 0 ? (estado.txt + ' $0') : (estado.txt + ' ' + fmt(abs))"></span>
  <div class="text-xs text-gray-500 mt-1">Contra <b>total contado</b> del día.</div>
</div>

    <div class="mt-5" style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
      <button type="button" class="bg-gray-300 px-4 py-2 rounded"
              wire:click="$set('mostrarModalCerrarCaja', false)">Cancelar</button>
      <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded"
              style="background:#4f46e5" wire:click="confirmarCerrarCaja">Cerrar</button>
    </div>
  </div>
</div>
@endif


</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openPrintPopup(url) {
        // Reusar el mismo popup entre impresiones
        const name = 'POS_PRINT_POPUP';
        // Tamaño típico de ticket térmico; ajusta a tu impresora
        const w = 420,
            h = 680;
        const y = Math.max(0, Math.floor((window.screen.height - h) / 2));
        const x = Math.max(0, Math.floor((window.screen.width - w) / 2));

        const features = [
            `width=${w}`, `height=${h}`,
            `left=${x}`, `top=${y}`,
            'menubar=0', 'toolbar=0', 'location=0', 'status=0',
            'scrollbars=1', 'resizable=1'
        ].join(',');

        const popup = window.open(url, name, features);

        // Si el bloqueador de popups lo impide, fall back al mismo tab
        if (!popup) {
            window.location.assign(url);
            return;
        }

        try {
            popup.focus();
        } catch (_) {}

        // Intento de imprimir automáticamente cuando cargue
        const tryAutoPrint = () => {
            try {
                if (popup.document && popup.document.readyState === 'complete') {
                    popup.print?.();
                } else {
                    setTimeout(tryAutoPrint, 400);
                }
            } catch (_) {
                // Si es cross-origin, no podemos acceder al DOM; el servidor debería disparar print()
            }
        };
        setTimeout(tryAutoPrint, 600);
    }


    {
        // reemplazar cualquier script previo por éste
        (function() {
            // obtiene elemento contenedor Livewire relativo al botón
            function findLivewireProxyFromButton(buttonId) {
                try {
                    const btn = document.getElementById(buttonId);
                    if (!btn) return null;
                    // buscar el ancestro con atributo wire:id
                    const ancestor = btn.closest('[wire\\:id], [wire\\:initial-data]');
                    if (!ancestor) return null;
                    const wireId = ancestor.getAttribute('wire:id') || ancestor.getAttribute('wire:initial-data');
                    if (!wireId) return null;
                    if (window.Livewire && typeof window.Livewire.find === 'function') {
                        try {
                            return window.Livewire.find(wireId);
                        } catch (e) {
                            console.warn('Livewire.find failed', e);
                        }
                    }
                    if (window.livewire && typeof window.livewire.find === 'function') {
                        try {
                            return window.livewire.find(wireId);
                        } catch (e) {
                            console.warn('legacy livewire.find failed', e);
                        }
                    }
                } catch (e) {
                    console.error('findLivewireProxyFromButton error', e);
                }
                return null;
            }

            // espera hasta tener proxy (reintentos)
            async function waitProxy(buttonId, timeout = 2500) {
                const start = Date.now();
                while (Date.now() - start < timeout) {
                    const p = findLivewireProxyFromButton(buttonId);
                    if (p) return p;
                    await new Promise(r => setTimeout(r, 120));
                }
                return findLivewireProxyFromButton(buttonId);
            }

            window.uiAbrirFacturar = async function() {
                try {
                    const wire = await waitProxy('btn-abrir-facturar', 2500);
                    if (!wire) {
                        console.error(
                            'Livewire proxy carrito-venta no encontrado. Ver consola y asegúrate de que el componente renderice [wire:id].'
                            );
                        Swal.fire('Error',
                            'Componente Livewire (carrito-venta) no disponible. Revisa la consola.',
                            'error');
                        return;
                    }

                 let itemsEnCarrito = 0;
let total = 0;
let credito = { permite:false, limite:0, deuda:0, dias:0, cupo_disponible:0, nombre:'' };

try {
  if (wire && typeof wire.get === 'function') {
    // carrito
    let carrito = wire.get('carrito') ?? [];
    if (!Array.isArray(carrito)) { try{ carrito = Object.values(carrito); } catch(_) { carrito = []; } }
    itemsEnCarrito = Array.isArray(carrito) ? carrito.length : 0;

    // total
    const tg = wire.get('totalGeneral');
    total = (tg !== undefined && tg !== null) ? Number(tg) || 0
           : carrito.reduce((s, it) => s + (Number(it.total ?? it.subtotal ?? it.precio ?? 0) || 0), 0);

    // 👇 **AQUÍ EL CAMBIO IMPORTANTE**
    // Pedimos el crédito al servidor, basado en el cliente actual del componente
    credito = await wire.call('uiCreditoActual');   // <- usa el método público
  }
} catch (e) {
  console.warn('[POS] No se pudo leer datos desde Livewire, usando defaults.', e);
}

console.debug('[POS] clienteId=', (typeof wire?.get==='function' ? wire.get('clienteId') : null));
console.debug('[POS] credito (server)=', credito);
console.debug('[POS] total=', total);


                    // fallback a valores server-render si no se encontraron
                    if (!itemsEnCarrito) {
                        itemsEnCarrito = Number({{ $itemsActivos ?? 0 }}) || 0;
                    }
                    if (!total) {
                        total = Number({{ $totalGeneral ?? 0 }}) || 0;
                    }
                    // leer cajaEstado desde proxy si existe
                    let cajaEstado = null;
                    try {
                        cajaEstado = (typeof wire.get === 'function') ? wire.get('cajaEstado') : null;
                    } catch (e) {
                        cajaEstado = null;
                    }

                    if (cajaEstado !== 'abierta') {
                        const r = await Swal.fire({
                            title: 'Caja cerrada',
                            text: 'La caja está cerrada. ¿Deseas abrirla ahora para continuar con la venta?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Abrir caja',
                            cancelButtonText: 'Cancelar',
                            reverseButtons: true,
                            html: `<div style="margin-top:8px;text-align:left">
                                  <label style="font-size:12px;display:block;margin-bottom:6px">Monto apertura</label>
                                  <input id="sw_monto_apertura" class="swal2-input" type="number" min="0" value="0">
                                </div>`
                        });
                        if (!r.isConfirmed) return;
                        const monto = parseFloat(document.getElementById('sw_monto_apertura')?.value ||
                            '0') || 0;
                        try {
                            await wire.call('abrirCajaConMonto', monto);
                            Swal.fire({
                                icon: 'success',
                                title: 'Caja abierta',
                                text: `Apertura: $${Number(monto).toLocaleString('es-CO')}`,
                                timer: 1400,
                                showConfirmButton: false
                            });
                        } catch (e) {
                            console.error('abrirCajaConMonto error', e);
                            Swal.fire('Error', 'No se pudo abrir la caja.', 'error');
                            return;
                        }
                    }

                    if (itemsEnCarrito <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Carrito vacío',
                            text: 'Agrega productos antes de facturar.'
                        });
                        return;
                    }
                    
                    // abrir modal facturación (igual a tu UI)
                    Swal.fire({
          title: 'Facturar',
          html: `
            <div style="text-align:center;max-width:360px;margin:auto;font-size:14px">
              <div style="margin-bottom:10px">
                <div style="font-size:11px;color:#6b7280">TOTAL</div>
                <div id="swal_total" style="font-weight:800;font-size:22px;line-height:1">$${total.toLocaleString('es-CO')}</div>
              </div>

              <div style="display:flex;flex-direction:column;gap:.5rem;align-items:center">
                <div style="width:100%;max-width:280px;text-align:left">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Tipo de factura</label>
                  <select id="swal_tipo_factura" class="swal2-input" style="width:100%;height:36px">
                    <option value="salida">Salida</option>
                    <option value="electronica">Electrónica</option>
                  </select>
                </div>

                <div style="width:100%;max-width:280px;text-align:left;margin-top:8px">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Tipo de pago</label>
                  <select id="swal_tipo_pago" class="swal2-input" style="width:100%;height:36px"></select>
                </div>

                <div id="box_medio_pago" style="width:100%;max-width:280px;text-align:left;margin-top:8px">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Medio de pago</label>
                  <select id="swal_medio_pago" class="swal2-input" style="width:100%;height:36px">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="otro">Otro</option>
                  </select>
                </div>

                <div id="box_recibido" style="display:none;width:100%;max-width:280px;text-align:center;margin-top:8px">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px;text-align:left">Importe recibido (efectivo)</label>
                  <input id="swal_recibido" type="text" class="swal2-input" placeholder="0" style="text-align:right">
                  <div style="font-size:12px;color:#6b7280;margin-top:6px">Vueltos: <b id="swal_vueltos">$0</b></div>
                </div>

                <div id="box_transfer_obs" style="display:none;width:100%;max-width:280px;text-align:left;margin-top:8px">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Observación (banco / cuenta)</label>
                  <input id="swal_transfer_obs" type="text" class="swal2-input" placeholder="Ej: Bancolombia - Ahorros ****1234">
                </div>

                <div id="box_credito_msg" style="display:none;width:100%;max-width:280px;text-align:left;margin-top:8px;font-size:12px;color:#1f2937;background:#f3f4f6;padding:8px;border-radius:6px">
                  <div><b>Cliente:</b> ${credito?.nombre ?? ''}</div>
                  <div><b>Límite:</b> $${(Number(credito?.limite||0)).toLocaleString('es-CO')}</div>
                  <div><b>Usado:</b> $${(Number(credito?.deuda||0)).toLocaleString('es-CO')}</div>
                  <div><b>Disponible:</b> $${(Number(credito?.cupo_disponible||0)).toLocaleString('es-CO')}</div>
                </div>

                <div id="box_vencimiento" style="display:none;width:100%;max-width:280px;text-align:left;margin-top:8px">
                  <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Fecha de vencimiento (crédito)</label>
                  <input id="swal_fecha_venc" type="date" class="swal2-input">
                  <div id="help_venc" style="font-size:12px;color:#6b7280;margin-top:6px"></div>
                </div>
              </div>

              <div style="text-align:center;margin-top:14px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
                <button id="btnFacturar" type="button" class="swal2-confirm swal2-styled" style="background:#4f46e5">Facturar</button>
                <button id="btnImprimir" type="button" class="swal2-confirm swal2-styled" style="background:#10b981">Imprimir</button>
                <button id="btnCancelar" type="button" class="swal2-cancel swal2-styled">Cancelar</button>
              </div>
            </div>
          `,
          showConfirmButton: false,
          didOpen: () => {
            const tipoPago   = document.getElementById('swal_tipo_pago');
            const medio      = document.getElementById('swal_medio_pago');
            const boxMedio   = document.getElementById('box_medio_pago');
            const boxRec     = document.getElementById('box_recibido');
            const boxTrans   = document.getElementById('box_transfer_obs');
            const boxCredito = document.getElementById('box_credito_msg');
            const boxVenc    = document.getElementById('box_vencimiento');
            const inputRec   = document.getElementById('swal_recibido');
            const vueltosLbl = document.getElementById('swal_vueltos');
            const inputTrans = document.getElementById('swal_transfer_obs');

            // Construye opciones de Tipo de pago según crédito/cupo
            while (tipoPago.options.length) tipoPago.remove(0);
            tipoPago.add(new Option('Contado', 'contado'));

            const puedeCredito = Boolean(credito?.permite);
            const cupoOk = Number(credito?.cupo_disponible || 0) >= Number(total || 0);
            if (puedeCredito && cupoOk) {
              tipoPago.add(new Option('Crédito', 'credito'));
            } else if (puedeCredito && !cupoOk) {
              Swal.fire({
                icon:'info',
                title:'Cupo insuficiente',
                text:`Disponible: $${Number(credito.cupo_disponible||0).toLocaleString('es-CO')} — Total: $${Number(total||0).toLocaleString('es-CO')}`,
                timer:2500, showConfirmButton:false
              });
            }
            tipoPago.value = 'contado';

            // Helpers
            const applyVencLimits = () => {
              const inp  = document.getElementById('swal_fecha_venc');
              const help = document.getElementById('help_venc');
              if (!inp) return;
              const hoy = new Date();
              const dias = Number(credito?.dias || 0);
              const max = new Date(); max.setDate(max.getDate() + dias);
              inp.min = hoy.toISOString().slice(0,10);
              inp.max = max.toISOString().slice(0,10);
              if (!inp.value) inp.value = inp.max;
              if (help) help.textContent = `Máximo permitido: ${inp.max} (días: ${dias})`;
            };

            const validateCreditoFront = () => {
              if (tipoPago.value !== 'credito') return true;
              if (Number(credito?.cupo_disponible||0) < Number(total||0)) {
                Swal.fire('Cupo insuficiente','El total supera el cupo disponible.','warning');
                return false;
              }
              const inp = document.getElementById('swal_fecha_venc');
              if (!inp?.value) { Swal.fire('Fecha requerida','Selecciona la fecha de vencimiento.','warning'); return false; }
              if (inp.value > inp.max) { Swal.fire('Fecha inválida', `No puede superar ${inp.max}.`, 'warning'); return false; }
              return true;
            };

            const validateTransferObs = () => {
              if (tipoPago.value === 'contado' && medio?.value === 'transferencia') {
                const obs = (inputTrans?.value || '').trim();
                if (!obs) { inputTrans?.focus(); Swal.fire('Falta observación','Escribe banco/cuenta para la transferencia.','warning'); return false; }
              }
              return true;
            };

            const toggle = () => {
              if (tipoPago.value === 'contado') {
                boxMedio.style.display   = 'block';
                boxCredito.style.display = 'none';
                boxVenc.style.display    = 'none';
                if (medio.value === 'efectivo') {
                  boxRec.style.display   = 'block';
                  boxTrans.style.display = 'none';
                  inputTrans?.removeAttribute('required');
                } else if (medio.value === 'transferencia') {
                  boxRec.style.display   = 'none';
                  boxTrans.style.display = 'block';
                  inputTrans?.setAttribute('required','required');
                  inputTrans?.focus();
                } else {
                  boxRec.style.display   = 'none';
                  boxTrans.style.display = 'none';
                  inputTrans?.removeAttribute('required');
                }
              } else {
                // CRÉDITO ⇒ ocultar medio de pago
                boxMedio.style.display   = 'none';
                boxRec.style.display     = 'none';
                boxTrans.style.display   = 'none';
                inputTrans?.removeAttribute('required');
                boxCredito.style.display = 'block';
                boxVenc.style.display    = 'block';
                applyVencLimits();
              }
            };

            tipoPago.addEventListener('change', toggle);
            medio.addEventListener('change', toggle);

            inputRec?.addEventListener('input', (e) => {
              let valor = e.target.value.replace(/\D/g, '');
              e.target.value = valor ? parseInt(valor).toLocaleString('es-CO') : '';
              const v = Math.max(0, (parseFloat(valor||'0')||0) - Number(total||0));
              if (vueltosLbl) vueltosLbl.textContent = '$' + v.toLocaleString('es-CO');
            });

            // Estado inicial
            toggle();

            // Botones
            document.getElementById('btnFacturar')?.addEventListener('click', () => {
              if (tipoPago.value === 'credito' && !validateCreditoFront()) return;
              if (!validateTransferObs()) return;

              const medioSel = medio?.value || null;
              const payload = {
                tipo_factura: document.getElementById('swal_tipo_factura').value,
                tipo_pago: tipoPago.value,
                medio_pago: (tipoPago.value === 'contado') ? medioSel : null,
                recibido: (tipoPago.value === 'contado' && medioSel === 'efectivo')
                  ? (document.getElementById('swal_recibido')?.value || '').replace(/\D/g,'')
                  : null,
                transferencia_obs: (tipoPago.value === 'contado' && medioSel === 'transferencia')
                  ? (document.getElementById('swal_transfer_obs')?.value || '').trim()
                  : null,
                fecha_vencimiento: (tipoPago.value === 'credito')
                  ? (document.getElementById('swal_fecha_venc')?.value || null)
                  : null,
                total: total
              };

              Swal.close();
              wire.call('facturarConfirmada', payload).catch(e => {
                console.error('facturarConfirmada error', e);
                Swal.fire('Error','No se pudo facturar.','error');
              });
            });

            document.getElementById('btnImprimir')?.addEventListener('click', async () => {
              if (tipoPago.value === 'credito' && !validateCreditoFront()) return;
              if (!validateTransferObs()) return;

              const tp       = tipoPago.value;
              const medioSel = medio?.value || null;

              const payload = {
                tipo_factura: document.getElementById('swal_tipo_factura').value,
                tipo_pago: tp,
                medio_pago: (tp === 'contado') ? medioSel : null,
                recibido: (tp === 'contado' && medioSel === 'efectivo')
                  ? (document.getElementById('swal_recibido')?.value || '').replace(/\D/g,'')
                  : null,
                transferencia_obs: (tp === 'contado' && medioSel === 'transferencia')
                  ? (document.getElementById('swal_transfer_obs')?.value || '').trim()
                  : null,
                fecha_vencimiento: (tp === 'credito')
                  ? (document.getElementById('swal_fecha_venc')?.value || null)
                  : null,
              };

              Swal.close();
              try {
                const res  = await wire.call('facturarEImprimir', payload);
                const data = (res && typeof res === 'object') ? res : null;
                if (data?.print_url) openPrintPopup(data.print_url);
                else Swal.fire('Listo','Factura creada. No se encontró URL de impresión.','info');
              } catch (e) {
                console.error('facturarEImprimir error', e);
                Swal.fire('Error','No se pudo facturar/imprimir.','error');
              }
            });

            document.getElementById('btnCancelar')?.addEventListener('click', () => Swal.close());
          }
        });

      } catch (err) {
        console.error('uiAbrirFacturar ERROR', err);
        Swal.fire('Error', 'No se pudo abrir modal de facturación.', 'error');
      }
    };

  })();

   }



    document.addEventListener('livewire:load', () => {


         window.addEventListener('open-print', e => {
    const url = e.detail?.url; if (!url) return;
    const w = window.open(url, '_blank', 'width=420,height=700'); if (w) w.focus();
  });


        // ✅ MENSAJES ÉXITO AL GUARDAR CLIENTE
        window.addEventListener('cliente-creado', () => {
            Swal.fire({
                icon: 'success',
                title: 'Cliente creado',
                text: 'El nuevo cliente ha sido registrado correctamente.',
                timer: 3000,
                showConfirmButton: false
            });
        });

        window.addEventListener('ui-abonar-factura', (ev) => {
            const {
                id,
                saldo
            } = ev.detail || {};
            Swal.fire({
                title: `Abonar a #${id}`,
                html: `
                        <div style="text-align:left">
                          <label class='block text-xs font-semibold'>Monto a abonar</label>
                          <input id="ab_monto" type="number" min="1" step="0.01" max="${saldo}" class="swal2-input" style="width:100%;height:34px" placeholder="0">
                          <label class='block text-xs font-semibold'>Medio</label>
                          <select id="ab_medio" class="swal2-input" style="width:100%;height:34px">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="otro">Otro</option>
                          </select>
                          <label class='block text-xs font-semibold'>Nota</label>
                          <input id="ab_nota" type="text" class="swal2-input" style="width:100%;height:34px" placeholder="Abono cartera">
                          <div class="text-xs text-gray-600 mt-1">Saldo actual: <b>$${Number(saldo||0).toLocaleString('es-CO')}</b></div>
                        </div>
                      `,
                showCancelButton: true,
                confirmButtonText: 'Registrar abono',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const monto = parseFloat(document.getElementById('ab_monto')?.value ||
                        '0');
                    const medio = document.getElementById('ab_medio')?.value || 'efectivo';
                    const nota = document.getElementById('ab_nota')?.value ||
                        'Abono cartera';
                    if (!monto || monto <= 0) {
                        Swal.showValidationMessage('Ingrese un monto válido.');
                        return false;
                    }
                    if (monto > Number(saldo || 0)) {
                        Swal.showValidationMessage('El monto supera el saldo.');
                        return false;
                    }
                    return {
                        factura_id: id,
                        monto,
                        medio,
                        nota
                    };
                }
            }).then(res => {
                if (!res.isConfirmed) return;
                $wire.confirmarAbono(res.value);
            });
        });

        // 🗑️ CONFIRMAR BORRADO DE PREFACTURA
        Livewire.on('confirmar-borrar-prefactura', () => {
            Swal.fire({
                title: '¿Borrar prefactura?',
                text: 'Esta acción eliminará la prefactura seleccionada. ¿Estás seguro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, borrar',
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

        // ✅ PREFACTURA BORRADA
        Livewire.on('prefactura-borrada', () => {
            Swal.fire({
                title: 'Prefactura eliminada',
                text: 'La prefactura se eliminó correctamente.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });

        // 🔄 REABRIR MODAL DE PREFACTURAS
        Livewire.on('reabrir-modal-prefacturas', () => {
            setTimeout(() => {
                Livewire.dispatch('abrirModalPrefacturas');
            }, 100);
        });

        // 📝 ACTUALIZAR OBSERVACIONES desde otro componente
        Livewire.on('actualizar-observaciones', ({
            texto
        }) => {
            const campo = document.querySelector(
                'textarea[wire\\:model\\.defer="observacionesPrefactura"]');
            if (campo) campo.value = texto;
        });

        // 💾 CONFIRMAR GUARDADO DE PREFACTURA
        Livewire.on('confirmar-guardar-prefactura', () => {
            Swal.fire({
                title: '¿Guardar prefactura?',
                text: '¿Desea guardar esta prefactura con los productos seleccionados?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#38a169',
                cancelButtonColor: '#e53e3e',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch('guardar-prefactura-confirmada');
                }
            });
        });

        // 💾 GUARDAR AUTOMÁTICAMENTE EL CARRITO EN CACHE
        Livewire.hook('message.processed', (message, component) => {
            try {
                const carrito = component.get('carrito');
                if (carrito) {
                    localStorage.setItem('carrito_venta', JSON.stringify(carrito));
                }
            } catch (e) {
                console.error("Error guardando carrito automáticamente:", e);
            }
        });

        // 🔄 SYNC CACHE MANUAL EXTERNO
        Livewire.on('guardar-carrito-en-cache', carrito => {
            Alpine.store('carrito').guardar(carrito);
        });

        Livewire.on('limpiar-carrito-en-cache', () => {
            Alpine.store('carrito').limpiar();
        });

        Livewire.on('actualizar-cache-carrito', (carrito) => {
            localStorage.setItem('carrito_venta', JSON.stringify(carrito));
        });

    });
</script>








    

    


    





















    

    





    

    




























































