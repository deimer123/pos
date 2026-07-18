<div class="space-y-6">

    <div class="flex items-center justify-between gap-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
        <div>
            <div class="font-semibold text-gray-800">1. Descarga la plantilla</div>
            <div class="text-sm text-gray-600">Baja el modelo en Excel (trae ejemplo e instrucciones), llénalo con tus productos y luego súbelo abajo.</div>
        </div>
        <x-filament::button wire:click="descargarPlantilla" color="primary" icon="heroicon-o-arrow-down-tray">
            Descargar plantilla
        </x-filament::button>
    </div>

    <form wire:submit.prevent="importar" class="space-y-4 rounded-xl border border-gray-200 p-4">
        <div>
            <label for="archivo" class="block font-medium text-sm text-gray-700">2. Sube tu archivo ya lleno</label>
            <input type="file" wire:model="archivo" id="archivo" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md mt-1" />
            @error('archivo') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <x-filament::button type="submit" color="success" wire:loading.attr="disabled" wire:target="importar,archivo">
                <span wire:loading.remove wire:target="importar">Importar productos</span>
                <span wire:loading wire:target="importar">Importando...</span>
            </x-filament::button>
        </div>
    </form>

    @if(!empty($resultado))
        <div class="rounded-xl border border-gray-200 p-4 space-y-2">
            <div class="font-semibold text-green-700">{{ $resultado['creados'] }} producto{{ $resultado['creados'] === 1 ? '' : 's' }} creado{{ $resultado['creados'] === 1 ? '' : 's' }}.</div>

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
