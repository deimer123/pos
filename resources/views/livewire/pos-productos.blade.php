<div style="width: 100%; height: 100%; display: flex; flex-direction: column; background: white;">
    @php
        $tipoNegocio = $empresaContexto['tipo_negocio'] ?? 'tienda';
        $placeholderBusqueda = $tipoNegocio === 'carniceria'
            ? 'Buscar corte, codigo o producto por peso...'
            : 'Buscar producto...';
    @endphp

    <div style="padding: 1rem; border-bottom: 1px solid #ddd; text-align: center; font-weight: bold; font-size: 1.5rem;">
        <input type="text" wire:model.live="search" placeholder="{{ $placeholderBusqueda }}"
            class="w-full p-2 border border-gray-300 rounded-full shadow focus:ring focus:ring-blue-200" />

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
        <div class="pos-products-grid grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));">
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
                $stockCantidad = (float) ($product->existencias ?? 0);
                $stockTexto = number_format($stockCantidad, $decimalesStock, ',', '.') . ' ' . $stockUnidad;
                $stockBadgeClasses = match (true) {
                    $stockCantidad < 0 => 'border-red-200 bg-red-50 text-red-700',
                    $stockCantidad == 0.0 => 'border-slate-200 bg-slate-100 text-slate-700',
                    $stockCantidad <= 5 => 'border-amber-200 bg-amber-100 text-amber-800',
                    default => 'border-emerald-200 bg-emerald-100 text-emerald-800',
                };
            @endphp

            <div wire:key="producto-{{ $product->id_producto }}" class="h-full">
                <div
                    class="pos-product-card-desktop h-full bg-white rounded-lg shadow border {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }}">
                    <div class="grid h-full justify-items-center px-3 py-3 text-center" style="min-height: 244px; grid-template-rows: 28px 52px 40px 42px 72px;">
                        <div class="inline-flex min-h-[24px] min-w-[62px] items-center justify-center self-center rounded-full border px-3 text-[11px] font-bold leading-none shadow-sm"
                            style="border-color:#1e293b;background:#1e293b;color:#ffffff;">
                            {{ $product->id_producto }}
                        </div>

                        <div class="relative z-10 flex h-[48px] items-center justify-center self-center bg-white">
                            <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                                class="w-12 h-12 object-cover border rounded cursor-pointer hover:opacity-80"
                                alt="Foto del producto" />
                        </div>

                        <div class="relative z-10 flex h-[36px] w-full items-center justify-center self-center overflow-hidden bg-white px-1 text-center">
                            <button type="button"
                                title="{{ $product->descripcion_larga }}"
                                @click="$dispatch('ver-nombre-producto-mobile', { nombre: @js($product->descripcion_larga), stock: @js($stockTexto) })"
                                class="flex h-[36px] w-full items-center justify-center overflow-hidden text-center text-[7px] font-semibold leading-[1.05] text-slate-700 break-words"
                                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; text-overflow: ellipsis;">
                                {{ $product->descripcion_larga }}
                            </button>
                        </div>

                        <div class="relative z-10 flex h-[40px] items-center justify-center self-center bg-white">
                            <div class="inline-flex min-w-[124px] items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-[13px] font-extrabold text-indigo-700 whitespace-nowrap shadow-sm">
                                <span>${{ number_format($product->precio_venta1, 0, ',', '.') }}</span>
                                <span class="text-[9px] font-semibold text-indigo-500">{{ $sufijoVenta }}</span>
                            </div>
                        </div>

                        <div class="relative z-10 flex h-[72px] w-full flex-col items-center justify-center self-center gap-2.5 bg-white">
                            <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                class="w-[84px] bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 text-[11px] rounded-full shadow">
                                Agregar
                            </button>

                            <div class="inline-flex min-h-[24px] min-w-[124px] items-center justify-center rounded-full border px-3 py-1 text-[10px] font-bold leading-tight text-center shadow-sm {{ $stockBadgeClasses }}">
                                Stock: {{ $stockTexto }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="pos-product-card-mobile h-full bg-white rounded-lg shadow border {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }}">
                    <div class="flex h-full flex-col items-center px-2 py-2.5 text-center" style="min-height: 168px;">
                        <div class="inline-flex min-h-[20px] min-w-[54px] items-center justify-center rounded-full border px-2.5 text-[10px] font-bold leading-none shadow-sm"
                            style="border-color:#1e293b;background:#1e293b;color:#ffffff;">
                            {{ $product->id_producto }}
                        </div>

                        <div class="relative z-10 mt-1 flex justify-center min-h-[40px] items-center bg-white">
                            <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                                class="h-10 w-10 rounded border object-cover" alt="Foto del producto" />
                        </div>

                        <div class="relative z-10 mt-1 flex w-full min-h-[2rem] items-center justify-center overflow-hidden bg-white px-1 text-center">
                            <button type="button"
                                title="{{ $product->descripcion_larga }}"
                                @click="$dispatch('ver-nombre-producto-mobile', { nombre: @js($product->descripcion_larga), stock: @js($stockTexto) })"
                                class="flex h-[2rem] w-full items-center justify-center overflow-hidden text-center text-[6px] font-semibold leading-[1.05] text-slate-700 break-words"
                                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; text-overflow: ellipsis;">
                                {{ $product->descripcion_larga }}
                            </button>
                        </div>

                        <div class="relative z-10 mt-1 flex min-h-[30px] items-center justify-center bg-white text-center">
                            <div class="inline-flex min-w-[92px] items-center justify-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-extrabold text-indigo-700 whitespace-nowrap shadow-sm">
                                <span>${{ number_format($product->precio_venta1, 0, ',', '.') }}</span>
                                <span class="text-[8px] font-semibold text-indigo-500">{{ $sufijoVenta }}</span>
                            </div>
                        </div>

                        <div class="relative z-10 mt-1 flex w-full flex-col items-center gap-1.5 bg-white">
                            <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                class="w-[84px] bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 text-[11px] rounded-full shadow">
                                Agregar
                            </button>

                            <div class="inline-flex min-h-[21px] min-w-[102px] items-center justify-center rounded-full border px-2.5 py-1 text-[9px] font-bold leading-tight text-center shadow-sm {{ $stockBadgeClasses }}">
                                Stock: {{ $stockTexto }}
                            </div>
                        </div>
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
