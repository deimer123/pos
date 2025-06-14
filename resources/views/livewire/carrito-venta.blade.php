<div>
    <div class="w-full md:w-1/3 bg-white p-4 border-l border-gray-300 h-screen overflow-y-auto shadow fixed right-0 top-0 z-50">
    <h2 class="text-xl font-bold mb-4">🛒 Carrito</h2>

    @foreach ($carrito as $item)
        <div class="mb-4 border-b pb-2">
            <p class="font-semibold text-sm">{{ $item['nombre'] }}</p>
            <p class="text-sm text-gray-500">Precio: ${{ number_format($item['precio'], 0, ',', '.') }}</p>
            <div class="flex items-center mt-1 gap-2">
                <input type="number" min="1" wire:change="actualizarCantidad('{{ $item['id'] }}', $event.target.value)"
                    value="{{ $item['cantidad'] }}" class="w-16 border rounded px-2 py-1 text-sm">
                <button wire:click="eliminarProducto('{{ $item['id'] }}')" class="text-red-600 text-xs">Eliminar</button>
            </div>
            <p class="text-right font-bold text-sm">Total: ${{ number_format($item['total'], 0, ',', '.') }}</p>
        </div>
    @endforeach

    <hr class="my-4">
    <div class="text-lg font-bold text-right">TOTAL: ${{ number_format($totalGeneral, 0, ',', '.') }}</div>
</div>

</div>
