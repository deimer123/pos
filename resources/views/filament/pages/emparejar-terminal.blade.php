<div class="space-y-6">

    <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <div class="text-sm text-gray-600">
            Genera un código de un solo uso, válido por 30 minutos, y escríbelo en la pantalla de Turión
            de la computadora que quieres emparejar como caja de este negocio.
        </div>

        <x-filament::button wire:click="generarCodigo" color="primary" icon="heroicon-o-key">
            Generar código de emparejamiento
        </x-filament::button>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 space-y-3">
        <div class="text-sm text-gray-600">
            ¿Todavía no tienes instalado Turión en esta computadora? Descarga la última versión.
        </div>

        <x-filament::button tag="a" href="{{ route('turion.descargar') }}" color="gray" icon="heroicon-o-arrow-down-tray">
            Descargar Turión
        </x-filament::button>
    </div>

    @if($codigoActivo)
        <div class="rounded-xl border border-green-200 bg-green-50 p-6 text-center space-y-2">
            <div class="text-sm text-gray-600">Código de emparejamiento</div>
            <div class="text-4xl font-mono font-bold tracking-widest text-green-800">{{ $codigoActivo->codigo }}</div>
            <div class="text-xs text-gray-500">Vence a las {{ $codigoActivo->expira_at->format('h:i A') }}</div>
        </div>
    @endif

</div>
