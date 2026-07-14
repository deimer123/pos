<div style="width: 100%; height: 100%; display: flex; flex-direction: column; background: white;">
    <script>window.posEmpresaContexto = @json($empresaContexto);</script>

    {{-- Todo este bloque es manejado por JS (busqueda/listado 100% local, ver
         resources/js/pos-catalogo-offline.js). wire:ignore evita que un
         re-render de este componente Livewire (ej. al abrir el modal de
         producto manual) borre lo que JS ya puso aqui. --}}
    <div wire:ignore>
        @php
            $tipoNegocio = $empresaContexto['tipo_negocio'] ?? 'tienda';
            $placeholderBusqueda = $tipoNegocio === 'carniceria'
                ? 'Buscar corte, codigo o producto por peso...'
                : 'Buscar producto...';
        @endphp

        <div style="padding: 0.75rem 1rem 0; border-bottom: 1px solid #ddd;">
            <div style="display:flex; align-items:center; gap:8px;">
                <input type="text" id="pos-buscar-producto" placeholder="{{ $placeholderBusqueda }}" autocomplete="off"
                    class="w-full p-2 border border-gray-300 rounded-full shadow focus:ring focus:ring-blue-200" />
                <button type="button" id="pos-actualizar-catalogo" title="Actualizar catalogo"
                    style="flex-shrink:0; width:36px; height:36px; border-radius:9999px; border:1px solid #d1d5db; background:white; cursor:pointer; font-size:15px;">
                    🔄
                </button>
            </div>
            <div id="pos-catalogo-sync-info" style="font-size:10px; color:#94a3b8; margin-top:4px;"></div>

            {{-- Filtro por tipo --}}
            @php
                $filtros = ['' => 'Todos', 'producto' => 'Productos', 'combo' => 'Combos'];
                if (!empty($empresaContexto['usa_recetas'])) $filtros['receta'] = 'Recetas';
                if (!empty($empresaContexto['usa_servicios'])) $filtros['servicio'] = 'Servicios';
                if (!empty($empresaContexto['usa_recetas'])) $filtros['insumo'] = 'Insumos';
            @endphp
            <div id="pos-filtros-tipo" style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; padding-bottom:8px;">
                @foreach($filtros as $val => $label)
                    <button type="button" data-filtro-tipo="{{ $val }}"
                        style="border:none; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;
                            background:{{ $val === '' ? '#2563eb' : '#e5e7eb' }};
                            color:{{ $val === '' ? 'white' : '#374151' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div style="flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 1rem;">
            <div id="pos-productos-grid" class="pos-products-grid grid gap-3" style="grid-template-columns: 1fr;"></div>
        </div>
    </div>

    <div x-data="{ imagenUrl: null }" @ver-imagen.window="imagenUrl = $event.detail.url">
        <div x-show="imagenUrl" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" x-transition>
            <div @click.outside="imagenUrl = null"
                class="bg-white p-3 rounded shadow w-[300px] h-[300px] flex items-center justify-center">
                <img :src="imagenUrl" class="w-60 h-60 object-contain rounded shadow" alt="Vista previa" />
            </div>
        </div>
    </div>

    <div x-data="{ nombreProductoMobile: null, stockProductoMobile: null }" @ver-nombre-producto-mobile.window="nombreProductoMobile = $event.detail.nombre; stockProductoMobile = $event.detail.stock">
        <div x-show="nombreProductoMobile" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" x-transition>
            <div @click.outside="nombreProductoMobile = null" class="w-full max-w-sm rounded-xl bg-white p-4 shadow-lg">
                <div class="text-center text-sm font-semibold text-slate-800 break-words" x-text="nombreProductoMobile"></div>
                <div x-show="stockProductoMobile" class="mt-3 flex justify-center">
                    <div class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 shadow-sm">
                        Stock: <span class="ml-1" x-text="stockProductoMobile"></span>
                    </div>
                </div>
                <div class="mt-4 flex justify-center">
                    <button @click="nombreProductoMobile = null; stockProductoMobile = null"
                        class="rounded-full bg-indigo-600 px-4 py-2 text-sm text-white shadow hover:bg-indigo-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($mostrarModalProductoManual)
        <div x-data="{ open: @entangle('mostrarModalProductoManual') }" x-show="open"
            @keydown.escape.window="open = false; $wire.set('mostrarModalProductoManual', false)"
            @click.outside="open = false; $wire.set('mostrarModalProductoManual', false)"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="bg-white rounded-xl shadow-lg p-6 w-[580px]">
                <h2 class="text-lg font-bold text-gray-800 text-center mb-4">Agregar producto temporal</h2>

                <table class="w-full text-sm" style="width: 100%;">
                    <tr class="align-top">
                        <td style="width: 75%;" class="pr-4">
                            <label class="block font-medium text-gray-700 mb-1">Nombre del producto <span
                                    class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="productoTemporal.nombre"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                required />
                            @error('productoTemporal.nombre')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </td>

                        <td style="width: 25%;">
                            <label class="block font-medium text-gray-700 mb-1">P.Venta <span
                                    class="text-red-500">*</span></label>
                            <input type="number" wire:model.defer="productoTemporal.precio"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                required />
                            @error('productoTemporal.precio')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </td>
                    </tr>
                </table>

                <div class="flex justify-center gap-6 mt-6">
                    <button @click="open = false; $wire.set('mostrarModalProductoManual', false)"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm rounded-full shadow">
                        Cancelar
                    </button>

                    <button wire:click="agregarProductoTemporal"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm rounded-full shadow">
                        Agregar al carrito
                    </button>
                </div>
            </div>
        </div>
    @endif

    @once
        <script>
            window.addEventListener('abrir-modal-temporal', () => {
                setTimeout(() => {
                    document.querySelector('input[wire\\:model\\.defer="productoTemporal.nombre"]')?.focus();
                }, 100);
            });

            document.addEventListener('DOMContentLoaded', () => {
                // Proteccion extra: aunque Livewire re-renderice este componente
                // (ej. al abrir el modal de producto manual) y este <script>
                // se re-ejecute, no se vuelven a enganchar los listeners.
                if (window.__posGridInicializado) return;
                window.__posGridInicializado = true;

                const Catalogo = window.PosCatalogoOffline;
                if (!Catalogo) return;

                const inputBusqueda = document.getElementById('pos-buscar-producto');
                const contenedorGrid = document.getElementById('pos-productos-grid');
                const botonesFiltro = document.querySelectorAll('#pos-filtros-tipo [data-filtro-tipo]');
                const botonActualizar = document.getElementById('pos-actualizar-catalogo');
                const infoSync = document.getElementById('pos-catalogo-sync-info');

                let filtroTipoActual = '';
                let debounceTimer = null;

                function wireComponent() {
                    // Busca el wire:id del propio componente PosProductos (no
                    // el primero que haya en la pagina, que podria ser otro
                    // componente como CarritoVenta).
                    const el = inputBusqueda.closest('[wire\\:id]');
                    return el ? window.Livewire.find(el.getAttribute('wire:id')) : null;
                }

                async function agregarItemMesaOffline(idProducto) {
                    await window.PosOfflineQueue.agregarOperacion({
                        tipo: 'mesa_item',
                        payload: {
                            mesa_id: window.posMesaId,
                            id_producto: idProducto,
                            cantidad_delta: 1,
                        },
                    });

                    const producto = Catalogo.getCatalogo().find((p) => String(p.id_producto) === String(idProducto));

                    window.Swal?.fire({
                        icon: 'success',
                        title: 'Agregado (pendiente de sincronizar)',
                        text: producto ? producto.descripcion_larga : ('Producto ' + idProducto),
                        timer: 1800,
                        showConfirmButton: false,
                    });
                }

                // La orden de taller se sincroniza completa cada vez (no
                // incremental, ver GuardarOrdenTallerService::sincronizarItems),
                // asi que hay que mandar siempre la lista completa de repuestos:
                // se parte de lo que ya tenia el carrito (cargado con conexion)
                // y se le van sumando los agregados offline.
                function itemsTallerActuales() {
                    if (!window.__posTallerItemsOffline) {
                        const wire = wireComponent();
                        const carrito = wire ? wire.get('carrito') : {};

                        window.__posTallerItemsOffline = Object.values(carrito || {}).map((item) => ({
                            id_producto: item.id_producto,
                            nombre: item.nombre,
                            cantidad: item.cantidad,
                            precio: item.nuevo_precio ?? item.precio,
                        }));
                    }

                    return window.__posTallerItemsOffline;
                }

                async function agregarItemTallerOffline(idProducto) {
                    const producto = Catalogo.getCatalogo().find((p) => String(p.id_producto) === String(idProducto));
                    if (!producto) return;

                    const items = itemsTallerActuales();
                    const existente = items.find((i) => String(i.id_producto) === String(idProducto));

                    if (existente) {
                        existente.cantidad += 1;
                    } else {
                        items.push({
                            id_producto: producto.id_producto,
                            nombre: producto.descripcion_larga,
                            cantidad: 1,
                            precio: producto.precio_venta1,
                        });
                    }

                    await window.PosOfflineQueue.agregarOperacion({
                        tipo: 'taller_item',
                        payload: {
                            taller_orden_id: window.posTallerOrdenId,
                            items,
                        },
                    });

                    window.Swal?.fire({
                        icon: 'success',
                        title: 'Agregado (pendiente de sincronizar)',
                        text: producto.descripcion_larga,
                        timer: 1800,
                        showConfirmButton: false,
                    });
                }

                // Mismo patron que taller: la reserva se sincroniza completa
                // cada vez (GuardarReservaService::sincronizarConsumos), sin
                // contar el item sintetico del hospedaje.
                function itemsHotelActuales() {
                    if (!window.__posHotelItemsOffline) {
                        const wire = wireComponent();
                        const carrito = wire ? wire.get('carrito') : {};

                        window.__posHotelItemsOffline = Object.values(carrito || {})
                            .filter((item) => !String(item.id_producto).startsWith('hotel-reserva-'))
                            .map((item) => ({
                                id_producto: item.id_producto,
                                nombre: item.nombre,
                                cantidad: item.cantidad,
                                precio: item.nuevo_precio ?? item.precio,
                            }));
                    }

                    return window.__posHotelItemsOffline;
                }

                async function agregarItemHotelOffline(idProducto) {
                    const producto = Catalogo.getCatalogo().find((p) => String(p.id_producto) === String(idProducto));
                    if (!producto) return;

                    const items = itemsHotelActuales();
                    const existente = items.find((i) => String(i.id_producto) === String(idProducto));

                    if (existente) {
                        existente.cantidad += 1;
                    } else {
                        items.push({
                            id_producto: producto.id_producto,
                            nombre: producto.descripcion_larga,
                            cantidad: 1,
                            precio: producto.precio_venta1,
                        });
                    }

                    await window.PosOfflineQueue.agregarOperacion({
                        tipo: 'hotel_item',
                        payload: {
                            hotel_reserva_id: window.posHotelReservaId,
                            items,
                        },
                    });

                    window.Swal?.fire({
                        icon: 'success',
                        title: 'Agregado (pendiente de sincronizar)',
                        text: producto.descripcion_larga,
                        timer: 1800,
                        showConfirmButton: false,
                    });
                }

                function agregarAlCarrito(idProducto) {
                    if (navigator.onLine === false) {
                        if (window.posMesaId) {
                            agregarItemMesaOffline(idProducto);
                            return;
                        }

                        if (window.posTallerOrdenId) {
                            agregarItemTallerOffline(idProducto);
                            return;
                        }

                        if (window.posHotelReservaId) {
                            agregarItemHotelOffline(idProducto);
                            return;
                        }

                        if (window.Swal) {
                            window.Swal.fire({
                                icon: 'warning',
                                title: 'Sin conexion',
                                text: 'No se puede agregar al carrito sin conexion.',
                                timer: 2200,
                                showConfirmButton: false,
                            });
                        }
                        return;
                    }

                    const wire = wireComponent();
                    if (wire) wire.call('agregarAlCarrito', idProducto);
                }

                function renderizar() {
                    const resultados = Catalogo.buscarLocal(inputBusqueda.value, filtroTipoActual);
                    Catalogo.renderizarProductos(resultados, window.posEmpresaContexto, contenedorGrid);
                }

                function actualizarInfoSync() {
                    const total = Catalogo.getCatalogo().length;
                    infoSync.textContent = total > 0 ? total + ' productos cargados localmente' : 'Sincronizando catalogo...';
                }

                Catalogo.inicializarEventosGrid(contenedorGrid, agregarAlCarrito);

                inputBusqueda.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    const valor = inputBusqueda.value.trim();

                    debounceTimer = setTimeout(() => {
                        if (valor === '10001') {
                            const wire = wireComponent();
                            if (wire) wire.call('abrirModalProductoManual');
                            inputBusqueda.value = '';
                            renderizar();
                            return;
                        }

                        const exacto = Catalogo.buscarCoincidenciaExacta(valor);
                        if (exacto) {
                            agregarAlCarrito(exacto.id_producto);
                            inputBusqueda.value = '';
                            renderizar();
                            return;
                        }

                        renderizar();
                    }, 120);
                });

                botonesFiltro.forEach((boton) => {
                    boton.addEventListener('click', () => {
                        filtroTipoActual = boton.getAttribute('data-filtro-tipo');

                        botonesFiltro.forEach((b) => {
                            const activo = b === boton;
                            b.style.background = activo ? '#2563eb' : '#e5e7eb';
                            b.style.color = activo ? 'white' : '#374151';
                        });

                        renderizar();
                    });
                });

                botonActualizar.addEventListener('click', async () => {
                    infoSync.textContent = 'Sincronizando...';
                    await Catalogo.sincronizarCatalogo();
                    actualizarInfoSync();
                    renderizar();
                });

                window.addEventListener('pos-catalogo-sincronizado', () => {
                    actualizarInfoSync();
                    renderizar();
                });

                Catalogo.cargarCatalogoLocal().then(() => {
                    actualizarInfoSync();
                    renderizar();
                });
            });
        </script>
    @endonce
</div>
