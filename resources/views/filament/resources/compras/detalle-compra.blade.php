@php
    /** @var \App\Models\Compra $compra */
    $money = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div class="rounded-lg border bg-white p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Proveedor</div>
            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}</div>
        </div>
        <div class="rounded-lg border bg-white p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Fecha</div>
            <div class="mt-1 text-sm font-semibold text-gray-900">{{ optional($compra->fecha)->format('d/m/Y') ?? '-' }}</div>
        </div>
        <div class="rounded-lg border bg-white p-4">
            <div class="text-xs font-semibold uppercase text-gray-500">Saldo</div>
            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $money($compra->saldo) }}</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-3 py-2 text-left">Código</th>
                    <th class="px-3 py-2 text-left">Producto</th>
                    <th class="px-3 py-2 text-right">Cant.</th>
                    <th class="px-3 py-2 text-right">Costo</th>
                    <th class="px-3 py-2 text-right">IVA</th>
                    <th class="px-3 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compra->detalles as $item)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $item->codigo_ingresado ?? $item->product_id }}</td>
                        <td class="px-3 py-2">{{ $item->nombre_producto ?? '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $item->cantidad, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($item->costo_unitario) }}</td>
                        <td class="px-3 py-2 text-right">{{ $money($item->impuesto ?? 0) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $money($item->total ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">Sin detalles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
