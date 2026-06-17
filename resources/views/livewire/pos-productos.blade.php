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
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
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
                $stockUnidad = match ((int) ($product->id_unidad_de_medida ?? 1)) {
                    2 => 'kg',
                    3 => 'lt',
                    4 => 'mt',
                    5 => 'hr',
                    default => 'und',
                };
                $decimalesStock = $stockUnidad === 'und' ? 0 : 2;
            @endphp

            <div wire:key="producto-{{ $product->id_producto }}" class="h-full">
                <div
                    class="pos-product-card-desktop bg-white rounded-lg shadow border {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }} {{ $product->existencias <= 0 ? 'opacity-60' : '' }}">
                    <div class="px-3 py-2">
                        <div class="grid grid-cols-[60px_68px_minmax(0,1fr)_120px_88px] items-center gap-3">
                            <div class="text-xs text-gray-600 text-center font-semibold">
                                <strong>{{ $product->id_producto }}</strong>
                            </div>

                            <div class="flex justify-center">
                                <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                                    class="w-16 h-16 object-cover border rounded cursor-pointer hover:opacity-80"
                                    alt="Foto del producto" />
                            </div>

                            <div class="min-w-0 text-sm text-gray-800">
                                <div class="leading-tight break-words">{{ $product->descripcion_larga }}</div>
                            </div>

                            <div class="text-sm font-bold text-right whitespace-nowrap">
                                <span>${{ number_format($product->precio_venta1, 0, ',', '.') }}</span>
                                <span class="text-[10px] font-medium text-slate-500">{{ $sufijoVenta }}</span>
                            </div>

                            <div class="text-center">
                                <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 text-xs rounded-full shadow">
                                    Agregar
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 pl-[131px] text-xs {{ $product->existencias > 0 ? 'text-green-600' : 'text-red-600' }}">
                            Stock: {{ number_format((float) $product->existencias, $decimalesStock, ',', '.') }} {{ $stockUnidad }}
                        </div>
                    </div>
                </div>

                <div
                    class="pos-product-card-mobile h-full bg-white rounded-lg shadow border {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }} {{ $product->existencias <= 0 ? 'opacity-60' : '' }}">
                    <div class="px-3 py-3">
                        <div class="grid grid-cols-2 gap-x-3 gap-y-2 items-start">
                            <div class="flex items-start gap-2 min-w-0">
                                <div class="w-10 pt-1 text-[12px] text-center font-semibold text-slate-500">
                                    {{ $product->id_producto }}
                                </div>
                                <div class="flex justify-center shrink-0">
                                    <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })" src="{{ $urlImagen }}"
                                        class="h-14 w-14 rounded border object-cover" alt="Foto del producto" />
                                </div>
                            </div>

                            <div class="min-w-0 pt-1 text-right">
                                <div class="whitespace-nowrap text-[13px] font-bold text-slate-700">
                                    ${{ number_format($product->precio_venta1, 0, ',', '.') }}
                                    <span class="text-[9px] font-medium text-slate-500">{{ $sufijoVenta }}</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="text-[12px] leading-tight text-slate-700 break-words">
                                    {{ $product->descripcion_larga }}
                                </div>
                            </div>

                            <div class="flex flex-col items-end justify-between gap-2">
                                <div class="text-[12px] text-right {{ $product->existencias > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    Stock: {{ number_format((float) $product->existencias, $decimalesStock, ',', '.') }} {{ $stockUnidad }}
                                </div>
                                <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 text-[12px] rounded-full shadow">
                                    Agregar
                                </button>
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
