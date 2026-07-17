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
                <button type="button" id="pos-verificar-precio" title="Verificar precio (no agrega al carrito)"
                    style="flex-shrink:0; width:36px; height:36px; border-radius:9999px; border:1px solid #d1d5db; background:white; cursor:pointer; font-size:15px;">
                    🏷️
                </button>
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

                function agregarAlCarrito(idProducto) {
                    if (navigator.onLine === false) {
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

                // Verificador de precios: busca en el catalogo guardado
                // localmente (funciona igual con o sin conexion) y solo
                // muestra nombre/precio -- no agrega nada al carrito ni
                // guarda nada, es solo para consultar. El resultado se arma
                // creando elementos DOM directamente (createElement/
                // textContent) en vez de strings de HTML, para no tener
                // ningun texto con pinta de etiqueta suelto en este archivo.
                const botonPrecio = document.getElementById('pos-verificar-precio');

                function formatoMonedaPrecio(valor) {
                    return '$' + Math.round(Number(valor) || 0).toLocaleString('es-CO');
                }

                function crearFilaPrecio(producto) {
                    const fila = document.createElement('div');
                    fila.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 4px;border-bottom:1px solid #e5e7eb;';

                    const info = document.createElement('div');
                    info.style.minWidth = '0';

                    const nombre = document.createElement('div');
                    nombre.style.cssText = 'font-weight:700;font-size:17px;color:#1e293b;';
                    nombre.textContent = producto.descripcion_larga;

                    const codigo = document.createElement('div');
                    codigo.style.cssText = 'font-size:14px;color:#94a3b8;';
                    codigo.textContent = 'Codigo ' + producto.id_producto;

                    info.appendChild(nombre);
                    info.appendChild(codigo);

                    const precio = document.createElement('div');
                    precio.style.cssText = 'font-size:20px;font-weight:800;color:#4338ca;white-space:nowrap;text-align:right;';
                    precio.textContent = formatoMonedaPrecio(producto.precio_venta1) + ' ' + (producto.sufijo_venta || '');

                    fila.appendChild(info);
                    fila.appendChild(precio);
                    return fila;
                }

                // Cuadro grande centrado: se usa cuando queda UN solo
                // resultado (tipico al escanear/escribir un codigo exacto),
                // para poder leer el precio de un vistazo sin achicar los
                // ojos. Con varias coincidencias (buscando por nombre) se
                // usa la lista de crearFilaPrecio en su lugar.
                function crearCuadroPrecio(producto) {
                    const cuadro = document.createElement('div');
                    cuadro.style.cssText = 'text-align:center;padding:18px 10px;';

                    const nombre = document.createElement('div');
                    nombre.style.cssText = 'font-weight:700;font-size:20px;color:#1e293b;margin-bottom:4px;';
                    nombre.textContent = producto.descripcion_larga;

                    const codigo = document.createElement('div');
                    codigo.style.cssText = 'font-size:14px;color:#94a3b8;margin-bottom:16px;';
                    codigo.textContent = 'Codigo ' + producto.id_producto;

                    const precio = document.createElement('div');
                    precio.style.cssText = 'font-size:52px;font-weight:800;color:#4338ca;line-height:1.1;';
                    precio.textContent = formatoMonedaPrecio(producto.precio_venta1);

                    const sufijo = document.createElement('div');
                    sufijo.style.cssText = 'font-size:15px;font-weight:600;color:#6366f1;margin-top:4px;';
                    sufijo.textContent = producto.sufijo_venta || '';

                    cuadro.appendChild(nombre);
                    cuadro.appendChild(codigo);
                    cuadro.appendChild(precio);
                    cuadro.appendChild(sufijo);
                    return cuadro;
                }

                botonPrecio?.addEventListener('click', () => {
                    // Modal fijo a casi toda la altura de la pantalla, con
                    // el campo de busqueda siempre visible arriba y solo la
                    // lista de resultados con scroll propio adentro -- asi
                    // el popup completo no se desplaza (antes al hacer
                    // scroll en la lista, el campo de busqueda tambien se
                    // iba hacia arriba y quedaba fuera de vista). Mismo
                    // patron que el modal de "Ingreso al taller" en
                    // carrito-venta.blade.php.
                    if (!document.getElementById('precio-modal-style')) {
                        const estilo = document.createElement('style');
                        estilo.id = 'precio-modal-style';
                        estilo.textContent = `
                            .swal-precio-popup { display:flex !important; flex-direction:column; height:92vh !important; max-height:92vh !important; }
                            .swal-precio-html { flex:1 1 auto; display:flex; flex-direction:column; min-height:0; overflow:hidden; margin:0 !important; }
                        `;
                        document.head.appendChild(estilo);
                    }

                    const campoInput = document.createElement('input');
                    campoInput.id = 'precio-buscar';
                    campoInput.type = 'text';
                    campoInput.className = 'swal2-input';
                    campoInput.placeholder = 'Nombre o codigo del producto...';
                    campoInput.autocomplete = 'off';
                    campoInput.style.cssText = 'width:100%;max-width:100%;height:56px;font-size:20px;margin-left:0;margin-right:0;box-sizing:border-box;flex-shrink:0;';

                    const contenedorResultados = document.createElement('div');
                    contenedorResultados.id = 'precio-resultados';
                    contenedorResultados.style.cssText = 'flex:1 1 auto;min-height:0;overflow-y:auto;text-align:left;margin-top:10px;';

                    const cuerpo = document.createElement('div');
                    cuerpo.style.cssText = 'display:flex;flex-direction:column;height:100%;min-height:0;';
                    cuerpo.appendChild(campoInput);
                    cuerpo.appendChild(contenedorResultados);

                    window.Swal.fire({
                        title: '🏷️ Verificar precio',
                        html: cuerpo,
                        width: 620,
                        heightAuto: false,
                        customClass: { popup: 'swal-precio-popup', htmlContainer: 'swal-precio-html' },
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: 'Cerrar',
                        didOpen: () => {
                            const input = document.getElementById('precio-buscar');
                            const resultados = document.getElementById('precio-resultados');

                            function render() {
                                resultados.replaceChildren();

                                // Codigo exacto (propio o alterno/barcode): se muestra
                                // solo ese, aunque la busqueda amplia tambien
                                // encuentre otros productos por una coincidencia
                                // parcial de barcode (ej. "10056" adentro de un
                                // codigo de barras mas largo de otro producto).
                                const exacto = Catalogo.buscarCoincidenciaExacta(input.value);
                                if (exacto) {
                                    resultados.appendChild(crearCuadroPrecio(exacto));
                                    return;
                                }

                                const lista = Catalogo.buscarLocal(input.value, '');

                                if (lista.length === 0) {
                                    const vacio = document.createElement('div');
                                    vacio.style.cssText = 'padding:16px;text-align:center;color:#94a3b8;font-size:16px;';
                                    vacio.textContent = 'Sin resultados';
                                    resultados.appendChild(vacio);
                                    return;
                                }

                                if (lista.length === 1) {
                                    resultados.appendChild(crearCuadroPrecio(lista[0]));
                                    return;
                                }

                                lista.forEach((producto) => resultados.appendChild(crearFilaPrecio(producto)));
                            }

                            input.addEventListener('input', render);
                            input.focus();
                            render();
                        },
                    });
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
