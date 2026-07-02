<div style="width: 100%; height: 100%; display: flex; flex-direction: column; background: white;">
    @php
        $tipoNegocio = $empresaContexto['tipo_negocio'] ?? 'tienda';
        $placeholderBusqueda = $tipoNegocio === 'carniceria'
            ? 'Buscar corte, codigo o producto por peso...'
            : 'Buscar producto...';
    @endphp

    <div style="padding: 0.75rem 1rem 0; border-bottom: 1px solid #ddd;">
        <input type="text" wire:model.live="search" placeholder="{{ $placeholderBusqueda }}"
            class="w-full p-2 border border-gray-300 rounded-full shadow focus:ring focus:ring-blue-200" />
        {{-- Filtro por tipo --}}
        @php
            $filtros = ['' => 'Todos', 'producto' => 'Productos', 'combo' => 'Combos'];
            if (!empty($empresaContexto['usa_recetas'])) $filtros['receta'] = 'Recetas';
            if (!empty($empresaContexto['usa_servicios'])) $filtros['servicio'] = 'Servicios';
            if (!empty($empresaContexto['usa_recetas'])) $filtros['insumo'] = 'Insumos';
        @endphp
        <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; padding-bottom:8px;">
            @foreach($filtros as $val => $label)
                <button wire:click="$set('filtroTipo','{{ $val }}')"
                    style="border:none; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;
                        background:{{ $filtroTipo === $val ? '#2563eb' : '#e5e7eb' }};
                        color:{{ $filtroTipo === $val ? 'white' : '#374151' }};">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <script>
            window.addEventListener('limpiar-input-busqueda', () => {
                const input = document.querySelector('input[wire\\:model\\.live="search"]');

                if (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                }
            });
        </script>
    </div>

    <div style="flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 1rem;">
        <div class="pos-products-grid grid gap-3" style="grid-template-columns: 1fr;">
        @forelse ($products as $product)
            @php
                $tieneImagen = !empty($product->foto) && $product->foto !== 'NULL' && $product->foto !== null;
                $urlImagen = $tieneImagen ? asset('storage/' . $product->foto) : asset('images/sin-imagen.png');
                $sufijoVenta = match ($product->vende_por ?? 'unidad') {
                    'peso' => '/ kg',
                    'porcion' => '/ porcion',
                    'litro' => '/ lt',
                    'metro' => '/ mt',
                    'hora' => '/ hr',
                    default => '/ und',
                };
                $stockUnidad = match ($product->vende_por ?? 'unidad') {
                    'peso' => 'kg',
                    'porcion' => 'porcion',
                    'litro' => 'lt',
                    'metro' => 'mt',
                    'hora' => 'hr',
                    default => match ((int) ($product->id_unidad_de_medida ?? 1)) {
                        2 => 'kg',
                        3 => 'lt',
                        4 => 'mt',
                        5 => 'hr',
                        default => 'und',
                    },
                };
                $decimalesStock = $stockUnidad === 'und' ? 0 : 2;

                // Si el producto tiene receta activa, mostrar porciones disponibles por ingredientes
                $recetaActiva = $product->recetaActiva ?? null;
                if ($recetaActiva === null) {
                    $recetaActiva = \App\Models\Receta::where('empresa_id', $product->empresa_id)
                        ->where('product_id', $product->id)
                        ->where('activo', true)
                        ->with('items.ingrediente')
                        ->first();
                }

                if ($recetaActiva) {
                    $stockCantidad = $recetaActiva->porciones_disponibles;
                    $stockUnidad = $recetaActiva->unidad_rendimiento;
                    $decimalesStock = 2;
                } else {
                    $stockCantidad = (float) ($product->existencias ?? 0);
                }

                $stockTexto = number_format($stockCantidad, $decimalesStock, ',', '.') . ' ' . $stockUnidad;
                $stockBadgeClasses = match (true) {
                    $stockCantidad <= 0 => 'border-red-200 bg-red-50 text-red-700',
                    $stockCantidad <= 5 => 'border-amber-200 bg-amber-100 text-amber-800',
                    default => 'border-emerald-200 bg-emerald-100 text-emerald-800',
                };
                $tieneStock = $stockCantidad > 0;
            @endphp

            <div wire:key="producto-{{ $product->id_producto }}">
                {{-- DESKTOP --}}
                <div class="pos-product-card-desktop bg-white rounded-lg shadow border {{ $tieneStock ? 'border-green-300' : 'border-red-200' }}"
                     style="height: 110px; display: flex; align-items: stretch;">

                    {{-- Columna izquierda: id + imagen, centrados verticalmente --}}
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:10px 8px 10px 14px; flex-shrink:0;">
                        <div class="inline-flex items-center justify-center rounded-full border px-2 text-[11px] font-bold leading-none shadow-sm"
                             style="border-color:#312e81;background:#4338ca;color:#fefefe; min-width:54px; height:22px;">
                            {{ $product->id_producto }}
                        </div>
                        <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                             style="width:56px; height:56px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; cursor:pointer;"
                             alt="Foto del producto" />
                    </div>

                    {{-- Columna nombre: flexible, centrado verticalmente --}}
                    <div style="flex:1; min-width:0; display:flex; align-items:center; padding:10px 12px 10px 8px;">
                        <div>
                            <div title="{{ $product->descripcion_larga }}"
                                 style="font-size:11px; font-weight:600; line-height:1.3; color:#334155; word-break:break-word; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
                                {{ $product->descripcion_larga }}
                            </div>
                            @if($product->tipo_producto === 'servicio' && $product->mecanico)
                            <div style="font-size:10px; color:#7c3aed; font-weight:700; margin-top:3px;">
                                🔧 {{ $product->mecanico->nombre }}
                            </div>
                            @elseif($product->tipo_producto === 'servicio' && $product->tercero_nombre)
                            <div style="font-size:10px; color:#d97706; font-weight:700; margin-top:3px;">
                                🤝 {{ $product->tercero_nombre }}
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Columna derecha: precio + stock + botón, siempre alineados al fondo --}}
                    <div style="display:flex; flex-direction:row; align-items:flex-end; gap:8px; padding:0 14px 10px 0; flex-shrink:0;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                            <div class="inline-flex items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 shadow-sm"
                                 style="width:120px; padding:5px 8px; font-size:12px; font-weight:800; color:#4338ca; white-space:nowrap;">
                                <span>${{ number_format($product->precio_venta1, 0, ',', '.') }}</span>
                                <span style="font-size:9px; font-weight:600; color:#6366f1;">{{ $sufijoVenta }}</span>
                            </div>
                            <div class="inline-flex items-center justify-center rounded-full border shadow-sm {{ $stockBadgeClasses }}"
                                 style="width:120px; padding:4px 8px; font-size:10px; font-weight:700; text-align:center;">
                                Stock: {{ $stockTexto }}
                            </div>
                        </div>
                        <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                style="width:88px; flex-shrink:0; background:#4f46e5; color:white; border:none; border-radius:9999px; padding:8px 6px; font-size:12px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2);"
                                onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                            Agregar
                        </button>
                    </div>
                </div>

                {{-- MOBILE --}}
                <div class="pos-product-card-mobile bg-white rounded-lg shadow border {{ $tieneStock ? 'border-green-300' : 'border-red-200' }}"
                     style="height: 96px; display:flex; align-items:stretch;">

                    {{-- Columna izquierda: id + imagen --}}
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; padding:8px 6px 8px 10px; flex-shrink:0;">
                        <div class="inline-flex items-center justify-center rounded-full border px-2 text-[10px] font-bold leading-none shadow-sm"
                             style="border-color:#312e81;background:#4338ca;color:#fefefe; min-width:46px; height:20px;">
                            {{ $product->id_producto }}
                        </div>
                        <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                             style="width:46px; height:46px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0;"
                             alt="Foto del producto" />
                    </div>

                    {{-- Columna nombre: flexible, centrado verticalmente --}}
                    <div style="flex:1; min-width:0; display:flex; align-items:center; padding:8px 8px 8px 6px;">
                        <div style="width:100%;">
                            <button type="button"
                                title="{{ $product->descripcion_larga }}"
                                @click="$dispatch('ver-nombre-producto-mobile', { nombre: @js($product->descripcion_larga), stock: @js($stockTexto) })"
                                style="width:100%; text-align:left; font-size:9px; font-weight:600; line-height:1.2; color:#334155; word-break:break-word; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; background:none; border:none; padding:0; cursor:pointer;">
                                {{ $product->descripcion_larga }}
                            </button>
                            @if($product->tipo_producto === 'servicio' && $product->mecanico)
                            <div style="font-size:9px; color:#7c3aed; font-weight:700; margin-top:2px;">🔧 {{ $product->mecanico->nombre }}</div>
                            @elseif($product->tipo_producto === 'servicio' && $product->tercero_nombre)
                            <div style="font-size:9px; color:#d97706; font-weight:700; margin-top:2px;">🤝 {{ $product->tercero_nombre }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Columna derecha: precio + stock + botón, alineados al fondo --}}
                    <div style="display:flex; flex-direction:row; align-items:flex-end; gap:6px; padding:0 10px 8px 0; flex-shrink:0;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:5px;">
                            <div class="inline-flex items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 shadow-sm"
                                 style="width:86px; padding:4px 6px; font-size:10px; font-weight:800; color:#4338ca; white-space:nowrap;">
                                <span>${{ number_format($product->precio_venta1, 0, ',', '.') }}</span>
                                <span style="font-size:7px; font-weight:600; color:#6366f1;">{{ $sufijoVenta }}</span>
                            </div>
                            <div class="inline-flex items-center justify-center rounded-full border shadow-sm {{ $stockBadgeClasses }}"
                                 style="width:86px; padding:3px 6px; font-size:8px; font-weight:700; text-align:center;">
                                Stock: {{ $stockTexto }}
                            </div>
                        </div>
                        <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                style="width:68px; flex-shrink:0; background:#4f46e5; color:white; border:none; border-radius:9999px; padding:6px 4px; font-size:10px; font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2);"
                                onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                            Agregar
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-4 space-y-4 min-h-[700px]">
                No se encontraron productos.
            </div>
        @endforelse
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
        </script>
    @endonce
</div>
