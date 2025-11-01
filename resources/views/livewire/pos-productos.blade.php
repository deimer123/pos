<div
    style="width: 45vw; height: 100vh; display: flex; flex-direction: column; border-right: 1px solid #ccc; background: white;">

    {{-- 🔍 Buscador superior --}}
    <div style="padding: 1rem; border-bottom: 1px solid #ddd; text-align: center; font-weight: bold; font-size: 1.5rem;">

        <input type="text" wire:model.live="search" placeholder="Buscar producto..."
            class="w-full p-2 border border-gray-300 rounded-full shadow focus:ring focus:ring-blue-200" />

        <script>
            window.addEventListener('limpiar-input-busqueda', () => {
                const input = document.querySelector('input[wire\\:model\\.live="search"]');

                if (input) {
                    // Establece el valor en blanco
                    input.value = '';

                    // Dispara evento input para que Livewire lo sincronice
                    input.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));

                    // Opcional: vuelve a enfocar el input para escribir más rápido
                    input.focus();
                }
            });
        </script>

    </div>

    {{-- 🧱 Cuadrícula de productos --}}
    <div style="flex: 1; overflow-y: auto; padding: 1rem;">
        @forelse ($products as $product)
            @php
                $tieneImagen = !empty($product->foto) && $product->foto !== 'NULL' && $product->foto !== null;
                $urlImagen = $tieneImagen ? asset('storage/' . $product->foto) : asset('images/sin-imagen.png');
            @endphp
            <div wire:key="producto-{{ $product->id_producto }}">

                <div
                    class="bg-white rounded-lg shadow p-2 border flex items-center justify-between gap-4
                {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }}
                {{ $product->existencias <= 0 ? 'opacity-60' : '' }}">
                    {{-- Código --}}
                    <div class="w-20 text-xs text-gray-600 text-center">
                        <strong>{{ $product->id_producto }}</strong>
                    </div>

                    {{-- Imagen --}}
                    <div class="w-20 flex-shrink-0">
                        <img wire:click="$dispatch('ver-imagen', { url: @js($urlImagen) })"
                            src="{{ $urlImagen }}"
                            class="w-16 h-16 object-cover border rounded cursor-pointer hover:opacity-80"
                            alt="Foto del producto" />
                    </div>

                    {{-- Descripción --}}
                    <div class="flex-1 text-sm text-gray-800">
                        {{ $product->descripcion_larga }}
                    </div>

                    {{-- Stock --}}
                    <div class="w-20 text-xs {{ $product->existencias > 0 ? 'text-green-600' : 'text-red-600' }}">
                        Stock: {{ $product->existencias }}
                    </div>

                    {{-- Precio --}}
                    <div class="w-24 text-sm font-bold text-right">
                        ${{ number_format($product->precio_venta1, 0, ',', '.') }}
                    </div>

                    {{-- Botón --}}
                    <div class="w-24 text-center">
                        <button wire:click="agregarAlCarrito({{ $product->id_producto }})"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 text-xs rounded-full shadow">
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

    {{-- 🖼️ Modal para ampliar imagen --}}
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

                {{-- Tabla de campos --}}
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

                {{-- Botones --}}
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
