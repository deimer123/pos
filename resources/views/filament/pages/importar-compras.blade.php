<div class="space-y-6">

    <form wire:submit.prevent="importar" class="space-y-6">

        <div class="compra-franja-azul bg-white">
            <div class="px-6 py-4">
                <div class="text-base font-bold text-gray-800">1. Datos de la factura</div>
            </div>

            <div class="compra-datos-linea-1 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="proveedor_id" class="block text-xs font-bold uppercase tracking-wide text-indigo-700">Proveedor</label>
                    <select wire:model="proveedor_id" id="proveedor_id" class="form-select w-full rounded-xl shadow-sm border-gray-300 mt-1">
                        <option value="">Seleccione un proveedor</option>
                        @foreach($this->getProveedores() as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('proveedor_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="numero_factura" class="block text-xs font-bold uppercase tracking-wide text-indigo-700">N° factura / compra</label>
                    <input type="text" wire:model="numero_factura" id="numero_factura" placeholder="Ej: A-12345" class="block w-full text-sm border border-gray-300 rounded-xl mt-1 px-3 py-2 shadow-sm" />
                    @error('numero_factura') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="tipo_pago" class="block text-xs font-bold uppercase tracking-wide text-indigo-700">Tipo de pago</label>
                    <select wire:model.live="tipo_pago" id="tipo_pago" class="form-select w-full rounded-xl shadow-sm border-gray-300 mt-1">
                        <option value="contado">Contado</option>
                        <option value="credito">Crédito</option>
                    </select>
                    @error('tipo_pago') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="compra-datos-linea-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="fecha" class="block text-xs font-bold uppercase tracking-wide text-amber-700">Fecha</label>
                    <input type="date" wire:model="fecha" id="fecha" class="block w-full text-sm border border-gray-300 rounded-xl mt-1 px-3 py-2 shadow-sm" />
                    @error('fecha') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                @if($tipo_pago === 'credito')
                    <div>
                        <label for="fecha_vencimiento" class="block text-xs font-bold uppercase tracking-wide text-amber-700">Vence</label>
                        <input type="date" wire:model="fecha_vencimiento" id="fecha_vencimiento" class="block w-full text-sm border border-gray-300 rounded-xl mt-1 px-3 py-2 shadow-sm" />
                        @error('fecha_vencimiento') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>
        </div>

        <div class="compra-franja-azul bg-white">
            <div class="flex items-center justify-between gap-4 px-6 py-5" style="background:#f0fdf4;">
                <div>
                    <div class="text-base font-bold text-gray-800">2. Descarga la plantilla de ítems</div>
                    <div class="text-sm text-gray-600 mt-1">Solo la lista de productos de esta factura (el encabezado ya lo llenaste arriba). <strong>Debes elegir el Proveedor antes de descargar</strong>: la plantilla solo trae los productos de ese proveedor. En la columna "Producto" el desplegable se filtra solo mientras escribes, y al elegir uno se llenan solos Costo e IVA. Tú escribes Cantidad, Descuento, Utilidad y (si el producto es nuevo) Departamento/Subfamilia/Unidad; el Precio de Venta se calcula solo.</div>
                </div>
                <x-filament::button wire:click="descargarPlantilla" type="button" color="success" icon="heroicon-o-arrow-down-tray">
                    Descargar plantilla
                </x-filament::button>
            </div>
        </div>

        <div class="compra-franja-azul bg-white">
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label for="archivo" class="block text-xs font-bold uppercase tracking-wide text-indigo-700">3. Sube tu archivo ya lleno</label>
                    <input type="file" wire:model="archivo" id="archivo" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-xl mt-1" />
                    @error('archivo') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="importar,archivo">
                        <span wire:loading.remove wire:target="importar">Importar compra</span>
                        <span wire:loading wire:target="importar">Importando...</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    </form>

    @if(!empty($resultado))
        @if($resultado['compra'])
            <div class="compra-franja-azul bg-white">
                <div class="px-6 py-4">
                    <div class="text-base font-bold text-green-700">✅ Compra #{{ $resultado['compra']->numero_factura }} creada correctamente</div>
                </div>

                <div class="compra-tot-linea-1">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 px-6 py-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">🧾 Ítems</div>
                            <div class="font-semibold text-gray-700 mt-1">{{ $resultado['items'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">📦 Cant. total</div>
                            <div class="font-semibold text-gray-700 mt-1">{{ number_format($resultado['cantidad_total'], 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">🆕 Productos nuevos / existentes</div>
                            <div class="font-semibold text-gray-700 mt-1">{{ $resultado['productos_nuevos'] }} nuevos · {{ $resultado['productos_existentes'] }} existentes</div>
                        </div>
                    </div>
                </div>

                <div class="compra-tot-linea-2">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 px-6 py-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Subtotal (sin descuento)</div>
                            <div class="font-semibold mt-1" style="color:#111827;">$ {{ number_format($resultado['compra']->subtotal, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">💸 Descuento comercial</div>
                            <div class="font-semibold mt-1" style="color:#b91c1c;">$ {{ number_format($resultado['compra']->descuento_total, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-gray-500">Subtotal con descuento (sin IVA)</div>
                            <div class="font-semibold mt-1" style="color:#0d9488;">$ {{ number_format($resultado['compra']->subtotal - $resultado['compra']->descuento_total, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-gray-500">IVA total</div>
                        <div class="font-semibold mt-1" style="color:#ca8a04;">$ {{ number_format($resultado['compra']->impuesto_total, 0, ',', '.') }}</div>
                    </div>
                    <div class="text-xl font-extrabold" style="color:#0f172a;">💰 TOTAL: $ {{ number_format($resultado['compra']->total, 0, ',', '.') }}</div>
                </div>
            </div>
        @else
            <div class="compra-franja-azul bg-white px-6 py-5">
                <div class="font-semibold text-red-700">No se creó ninguna compra: mientras haya una fila con error, no se crea nada (ni la factura ni los productos). Corrige lo de abajo y vuelve a subir el mismo archivo.</div>
            </div>
        @endif

        @if(!empty($resultado['errores']))
            <div class="compra-franja-azul bg-white px-6 py-5">
                <div class="font-semibold text-red-700">{{ count($resultado['errores']) }} fila(s) con error:</div>
                <ul class="list-disc list-inside text-sm text-red-600 max-h-64 overflow-y-auto mt-2">
                    @foreach($resultado['errores'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

</div>
