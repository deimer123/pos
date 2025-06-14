<div> {{-- <- Contenedor raíz del componente --}}

    {{-- 🔍 Título y buscador --}}
    <div class="sticky top-0 z-50 bg-white shadow px-4 py-3">
        <h1 class="text-xl font-bold text-indigo-700 flex items-center gap-2 mb-2">
            🛒 Punto de Venta
        </h1>
        <input
            type="text"
            wire:model.live="search"
            placeholder="Buscar producto..."
            class="w-full p-2 border border-gray-300 rounded shadow focus:ring focus:ring-blue-200"
        />
    </div>

    {{-- 🧱 Cuadrícula de productos --}}
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse ($products as $product)
        
        <div
    class="bg-white rounded-xl shadow hover:shadow-lg transition p-4 border relative
        {{ $product->existencias > 0 ? 'border-green-300' : 'border-red-200' }}
        {{ $product->existencias <= 0 ? 'opacity-60' : '' }}"
>
    <div class="flex items-center gap-4">
        {{-- Imagen --}}
        <img
            src="{{ !$product->foto || $product->foto === 'NULL' ? asset('images/sin-imagen.png') : asset('storage/' . $product->foto) }}"
            alt="{{ $product->descripcion_larga }}"
            class="w-20 h-20 object-cover rounded-lg border border-gray-300 mx-auto"
        >

        {{-- Contenido + Botón en columna --}}
        <div class="flex-1 flex flex-col justify-between text-center h-full">
            <div>
                <h2 class="font-bold text-lg text-gray-800">{{ $product->descripcion_larga }}</h2>
                <p class="text-sm text-gray-600">Código: {{ $product->id_producto }}</p>
                <p class="text-lg font-bold text-gray-900 mt-1">
                    ${{ number_format($product->precio_venta1, 0, ',', '.') }}
                </p>
                <p class="text-sm mt-1 {{ $product->existencias > 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold' }}">
                    Stock: {{ $product->existencias }}
                </p>
            </div>

            {{-- Botón Agregar con margen inferior --}}
            @if($product->existencias > 0)
                <button
                    wire:click="agregarAlCarrito({{ $product->id_producto }})"
                    class="mt-3 mb-3 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm rounded-full shadow font-semibold"
                >
                    Agregar
                </button>
            @endif
        </div>
    </div>
</div>

         
        
            
        @empty
            <div class="col-span-full text-center text-gray-500 py-10">
                No se encontraron productos.
            </div>
        @endforelse
    </div>

</div> {{-- <- Cierre del contenedor raíz --}}
