@php
    $existencia = is_numeric($exist)
        ? (float) $exist
        : (float) str_replace(['.', ','], ['', '.'], (string) $exist);
@endphp

<div
    class="text-xs py-1 border-b border-gray-200 hover:bg-gray-100 cursor-pointer"
    style="display: grid; grid-template-columns: 120px minmax(0, 1fr) 90px; align-items: center; gap: 12px;"
>
    <span class="font-mono text-gray-700 truncate" style="text-align: left;">
        {{ $codigo }}
    </span>

    <span class="text-gray-800 truncate" style="text-align: left;">
        {{ $nombre }}
    </span>

    <span class="font-semibold {{ $existencia > 0 ? 'text-green-600' : 'text-red-600' }}" style="text-align: right;">
        {{ number_format($existencia, 0, ',', '.') }}
    </span>
</div>
