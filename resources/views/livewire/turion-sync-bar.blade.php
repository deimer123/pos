<div @if($esTurion) wire:poll.60s="refrescarEstadoPeriodico" @endif>
@if($esTurion)
    <div class="flex items-center gap-2" wire:loading.class="opacity-60">
        @if($version)
            <span class="text-[10px] text-white/60 font-mono" title="Versión de Sistema POS Offline instalada">v{{ $version }}</span>
        @endif

        <div class="flex flex-col items-end leading-tight">
            @if(! $enLinea)
                <button type="button" disabled
                    class="bg-gray-400 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1 cursor-not-allowed"
                    title="Sin conexión con el droplet: no se puede sincronizar ahora.">
                    ⬇️ Sincronizar
                </button>
            @elseif($pendientes > 0)
                <button type="button" wire:click="sincronizar" wire:loading.attr="disabled" wire:target="sincronizar"
                    wire:confirm="Tienes {{ $pendientes }} cosa(s) pendiente(s) de subir. Sincronizar no las borra, pero es mejor darle 'Subir' primero. ¿Sincronizar de todas formas?"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1">
                    <span wire:loading.remove wire:target="sincronizar">⬇️ Sincronizar</span>
                    <span wire:loading wire:target="sincronizar">Sincronizando…</span>
                </button>
            @else
                <button type="button" wire:click="sincronizar" wire:loading.attr="disabled" wire:target="sincronizar"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1">
                    <span wire:loading.remove wire:target="sincronizar">⬇️ Sincronizar</span>
                    <span wire:loading wire:target="sincronizar">Sincronizando…</span>
                </button>
            @endif
            @if($ultimaSincronizacion)
                <span class="text-[10px] text-white/70 mt-0.5">Última: {{ $ultimaSincronizacion }}</span>
            @endif
        </div>

        <div class="flex flex-col items-end leading-tight">
            @if(! $enLinea)
                <button type="button" disabled
                    class="bg-gray-400 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1 cursor-not-allowed"
                    title="Sin conexión con el droplet: no se puede subir ahora.">
                    ⬆️ Subir @if($pendientes > 0)<span class="bg-white/25 rounded-full px-1.5">{{ $pendientes }}</span>@endif
                </button>
            @else
                <button type="button" wire:click="subir" wire:loading.attr="disabled" wire:target="subir"
                    class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1">
                    <span wire:loading.remove wire:target="subir">⬆️ Subir @if($pendientes > 0)<span class="bg-white/25 rounded-full px-1.5">{{ $pendientes }}</span>@endif</span>
                    <span wire:loading wire:target="subir">Subiendo…</span>
                </button>
            @endif
            @if($ultimaSubida)
                <span class="text-[10px] text-white/70 mt-0.5">Última: {{ $ultimaSubida }}</span>
            @endif
        </div>

        @if($mensaje)
            <span class="text-xs {{ $conError ? 'text-red-200' : 'text-white/80' }} max-w-[220px] truncate" title="{{ $mensaje }}">
                {{ $mensaje }}
            </span>
        @endif
    </div>
@endif
</div>
