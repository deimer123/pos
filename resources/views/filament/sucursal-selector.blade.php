@php
    $usuario = auth()->user();
    $servicio = app(\App\Services\Sucursales\SucursalActual::class);
    $opciones = $usuario ? $servicio->opciones($usuario) : collect();
    $actual = $usuario ? $servicio->actual($usuario) : null;
    $veTodas = $usuario && $servicio->veTodas($usuario);
@endphp

@if ($opciones->count() > 1)
    <form method="POST" action="{{ route('sucursal.cambiar') }}" class="sucursal-selector" style="display:flex;align-items:center;gap:.5rem;margin-inline-end:1rem;">
        @csrf
        <label for="sucursal-select" style="font-size:.8rem;font-weight:600;">Sucursal</label>
        <select id="sucursal-select" name="sucursal" onchange="this.form.submit()"
                style="border:1px solid #c7d2fe;border-radius:.5rem;padding:.25rem 1.75rem .25rem .5rem;font-size:.85rem;background:#fff;">
            @if ($veTodas)
                <option value="todas" @selected(! $actual)>Todas</option>
            @endif
            @foreach ($opciones as $opcion)
                <option value="{{ $opcion->id }}" @selected($actual?->id === $opcion->id)>{{ $opcion->nombre }}</option>
            @endforeach
        </select>
    </form>
@elseif ($opciones->count() === 1)
    <span style="font-size:.8rem;font-weight:600;margin-inline-end:1rem;">Sucursal: {{ $opciones->first()->nombre }}</span>
@endif
