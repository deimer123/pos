<x-filament::page>
    @php
        $productos = $this->utilidadProductos();
        $hospedaje = $this->utilidadHospedaje();
        $total = $productos + $hospedaje;
        $porHabitacion = $this->reportePorHabitacion();
        $ocupacion = $this->ocupacion();
        $money = fn ($valor) => '$ ' . number_format((float) $valor, 0, ',', '.');
    @endphp

    <div class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
            <input type="date" wire:model.live="desde"
                class="mt-1 block rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
            <input type="date" wire:model.live="hasta"
                class="mt-1 block rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-filament::section>
            <div class="text-sm text-gray-500">Utilidad Productos</div>
            <div class="text-2xl font-bold">{{ $money($productos) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Utilidad Hospedaje (100%)</div>
            <div class="text-2xl font-bold">{{ $money($hospedaje) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Utilidad Total</div>
            <div class="text-2xl font-bold">{{ $money($total) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Ocupación del rango</div>
            <div class="text-2xl font-bold">{{ number_format($ocupacion['porcentaje'], 2, ',', '.') }}%</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Ingresos por habitación" class="mb-8">
        @if(empty($porHabitacion))
            <p class="text-sm text-gray-500">No hay hospedajes facturados en este rango de fechas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Habitación</th>
                            <th class="py-2 pr-4">Reservas</th>
                            <th class="py-2 pr-4">Noches</th>
                            <th class="py-2 pr-4">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porHabitacion as $fila)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $fila['habitacion'] }}</td>
                                <td class="py-2 pr-4">{{ $fila['reservas'] }}</td>
                                <td class="py-2 pr-4">{{ $fila['noches'] }}</td>
                                <td class="py-2 pr-4">{{ $money($fila['ingresos']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Ocupación por habitación y día">
        @if($ocupacion['habitaciones']->isEmpty())
            <p class="text-sm text-gray-500">No hay habitaciones activas configuradas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-white dark:bg-gray-900 py-1 pr-3 text-left">Habitación</th>
                            @foreach($ocupacion['dias'] as $dia)
                                <th class="py-1 px-1 text-center font-normal text-gray-500">{{ $dia->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ocupacion['habitaciones'] as $habitacion)
                            <tr>
                                <td class="sticky left-0 bg-white dark:bg-gray-900 py-1 pr-3 font-medium whitespace-nowrap">{{ $habitacion->numero }}</td>
                                @foreach($ocupacion['dias'] as $dia)
                                    @php $ocupada = $ocupacion['ocupado'][$habitacion->id][$dia->toDateString()] ?? false; @endphp
                                    <td class="py-1 px-1 text-center">
                                        <span class="inline-block w-4 h-4 rounded {{ $ocupada ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700' }}"
                                            title="{{ $dia->format('d/m/Y') }} — {{ $ocupada ? 'Ocupada' : 'Libre' }}"></span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-green-500"></span> Ocupada</span>
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-gray-200 dark:bg-gray-700"></span> Libre</span>
            </div>
        @endif
    </x-filament::section>
</x-filament::page>
