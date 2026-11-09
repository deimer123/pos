@php
    // Solo el dueño con sucursales (y fuera de una sucursal) ve este acceso.
    $usuario = auth()->user();
    $visible = $usuario && \App\Filament\Pages\MisSucursales::canAccess();
    $disponibles = $visible
        ? app(\App\Services\Grupo\CambioDeEmpresa::class)->filasParaDueno($usuario)
            ->filter(fn ($fila) => $fila['motivo'] === null)
            ->map(fn ($fila) => $fila['modelo'])
            ->values()
        : collect();
@endphp

@if ($disponibles->isNotEmpty())
    <form method="POST" action="{{ route('grupo.entrar.seleccion') }}" style="display:flex;align-items:center;gap:.5rem;margin-inline-end:1rem;">
        @csrf
        @if ($disponibles->count() > 1)
            <select name="sucursal" aria-label="Sucursal"
                style="border:1px solid #c7d2fe;border-radius:.5rem;padding:.25rem 1.75rem .25rem .5rem;font-size:.8rem;background:#fff;">
                @foreach ($disponibles as $sucursal)
                    <option value="{{ $sucursal->id }}">{{ $sucursal->name }}</option>
                @endforeach
            </select>
            <button type="submit" style="border:1px solid #4f46e5;color:#fff;background:#4f46e5;border-radius:.5rem;padding:.25rem .75rem;font-size:.8rem;cursor:pointer;">
                Ir
            </button>
        @else
            <input type="hidden" name="sucursal" value="{{ $disponibles->first()->id }}">
            <button type="submit" title="Entrar a {{ $disponibles->first()->name }}" style="border:1px solid #4f46e5;color:#fff;background:#4f46e5;border-radius:.5rem;padding:.25rem .75rem;font-size:.8rem;cursor:pointer;">
                Ir a {{ $disponibles->first()->name }}
            </button>
        @endif
    </form>
@endif
