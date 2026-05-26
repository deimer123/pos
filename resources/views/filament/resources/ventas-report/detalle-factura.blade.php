@php
    $money = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.');
@endphp

<div class="space-y-4">
    <div class="grid gap-3 md:grid-cols-4">
        <div>
            <div class="text-xs font-medium text-gray-500">Fecha</div>
            <div class="font-semibold">{{ optional($factura->fecha)->format('d/m/Y H:i') }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500">Cliente</div>
            <div class="font-semibold">{{ $factura->cliente?->nombre ?? 'Consumidor final' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500">Vendedor</div>
            <div class="font-semibold">{{ $factura->vendedorAsignado?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="text-xs font-medium text-gray-500">Cajero</div>
            <div class="font-semibold">{{ $factura->cajero?->name ?? '-' }}</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-xs font-semibold uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <tr>
                    <th class="px-3 py-2 text-left">Codigo</th>
                    <th class="px-3 py-2 text-left">Producto</th>
                    <th class="px-3 py-2 text-right">Cantidad</th>
                    <th class="px-3 py-2 text-right">Precio</th>
                    <th class="px-3 py-2 text-right">Descuento</th>
                    <th class="px-3 py-2 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($factura->detalles as $detalle)
                    <tr>
                        <td class="px-3 py-2 tabular-nums">{{ $detalle->producto_id }}</td>
                        <td class="px-3 py-2">{{ $detalle->descripcion_larga }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $detalle->cantidad, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $money($detalle->precio) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $money($detalle->descuento) }}</td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $money($detalle->subtotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">Esta factura no tiene detalle registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end">
        <div class="min-w-64 rounded-lg bg-gray-50 p-4 text-right dark:bg-gray-800">
            <div class="text-xs font-medium text-gray-500">Total factura</div>
            <div class="text-2xl font-bold">{{ $money($factura->total) }}</div>
            <div class="mt-1 text-sm text-gray-500">Saldo: {{ $money($factura->saldo) }}</div>
        </div>
    </div>
</div>
