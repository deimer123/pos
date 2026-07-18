<div class="space-y-6">

    <form wire:submit.prevent="importar" class="space-y-6">

        <div class="rounded-xl border border-gray-200 p-4 space-y-4">
            <div class="font-semibold text-gray-800">1. Datos de la factura</div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="proveedor_id" class="block font-medium text-sm text-gray-700">Proveedor</label>
                    <select wire:model="proveedor_id" id="proveedor_id" class="form-select w-full rounded-md shadow-sm border-gray-300 mt-1">
                        <option value="">Seleccione un proveedor</option>
                        @foreach($this->getProveedores() as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('proveedor_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="numero_factura" class="block font-medium text-sm text-gray-700">N° factura / compra</label>
                    <input type="text" wire:model="numero_factura" id="numero_factura" placeholder="Ej: A-12345" class="block w-full text-sm border border-gray-300 rounded-md mt-1 px-3 py-2" />
                    @error('numero_factura') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="tipo_pago" class="block font-medium text-sm text-gray-700">Tipo de pago</label>
                    <select wire:model="tipo_pago" id="tipo_pago" class="form-select w-full rounded-md shadow-sm border-gray-300 mt-1">
                        <option value="contado">Contado</option>
                        <option value="credito">Crédito</option>
                    </select>
                    @error('tipo_pago') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="fecha" class="block font-medium text-sm text-gray-700">Fecha</label>
                    <input type="date" wire:model="fecha" id="fecha" class="block w-full text-sm border border-gray-300 rounded-md mt-1 px-3 py-2" />
                    @error('fecha') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                @if($tipo_pago === 'credito')
                    <div>
                        <label for="fecha_vencimiento" class="block font-medium text-sm text-gray-700">Vence</label>
                        <input type="date" wire:model="fecha_vencimiento" id="fecha_vencimiento" class="block w-full text-sm border border-gray-300 rounded-md mt-1 px-3 py-2" />
                        @error('fecha_vencimiento') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 rounded-xl border border-green-100 bg-green-50 p-4">
            <div>
                <div class="font-semibold text-gray-800">2. Descarga la plantilla de ítems</div>
                <div class="text-sm text-gray-600">Solo la lista de productos de esta factura (el encabezado ya lo llenaste arriba).</div>
            </div>
            <x-filament::button wire:click="descargarPlantilla" type="button" color="success" icon="heroicon-o-arrow-down-tray">
                Descargar plantilla
            </x-filament::button>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 space-y-4">
            <div>
                <label for="archivo" class="block font-medium text-sm text-gray-700">3. Sube tu archivo ya lleno</label>
                <input type="file" wire:model="archivo" id="archivo" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md mt-1" />
                @error('archivo') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="importar,archivo">
                    <span wire:loading.remove wire:target="importar">Importar compra</span>
                    <span wire:loading wire:target="importar">Importando...</span>
                </x-filament::button>
            </div>
        </div>
    </form>

    @if(!empty($resultado))
        <div class="rounded-xl border border-gray-200 p-4 space-y-2">
            @if($resultado['compra'])
                <div class="font-semibold text-green-700">Compra #{{ $resultado['compra']->numero_factura }} creada con {{ $resultado['items'] }} ítem{{ $resultado['items'] === 1 ? '' : 's' }}.</div>
            @else
                <div class="font-semibold text-red-700">No se creó ninguna compra.</div>
            @endif

            @if(!empty($resultado['errores']))
                <div class="font-semibold text-red-700">{{ count($resultado['errores']) }} fila(s) con error (no se importaron):</div>
                <ul class="list-disc list-inside text-sm text-red-600 max-h-64 overflow-y-auto">
                    @foreach($resultado['errores'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

</div>
