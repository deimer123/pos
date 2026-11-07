@php
    $enSucursal = app(\App\Services\Grupo\CambioDeEmpresa::class)->estaEnSucursal();
@endphp

@if ($enSucursal && auth()->check())
    <form method="POST" action="{{ route('grupo.volver') }}" style="display:flex;align-items:center;gap:.5rem;margin-inline-end:1rem;">
        @csrf
        <span style="font-size:.8rem;font-weight:600;">Sucursal: {{ auth()->user()->name }}</span>
        <button type="submit" style="border:1px solid #4f46e5;color:#fff;background:#4f46e5;border-radius:.5rem;padding:.25rem .75rem;font-size:.8rem;cursor:pointer;">
            Volver a mi empresa
        </button>
    </form>
@endif
