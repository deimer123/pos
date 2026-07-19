@if($esTurion)
<div class="flex items-center gap-2" wire:loading.class="opacity-60">
    <button type="button" wire:click="sincronizar" wire:loading.attr="disabled" wire:target="sincronizar"
        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1">
        <span wire:loading.remove wire:target="sincronizar">⬇️ Sincronizar</span>
        <span wire:loading wire:target="sincronizar">Sincronizando…</span>
    </button>

    <button type="button" wire:click="subir" wire:loading.attr="disabled" wire:target="subir"
        class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 text-xs rounded shadow inline-flex items-center gap-1">
        <span wire:loading.remove wire:target="subir">⬆️ Subir @if($pendientes > 0)<span class="bg-white/25 rounded-full px-1.5">{{ $pendientes }}</span>@endif</span>
        <span wire:loading wire:target="subir">Subiendo…</span>
    </button>

    @if($mensaje)
        <span class="text-xs {{ $conError ? 'text-red-200' : 'text-white/80' }} max-w-[220px] truncate" title="{{ $mensaje }}">
            {{ $mensaje }}
        </span>
    @endif
</div>
@endif
