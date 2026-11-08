<x-filament-panels::page>
    @if (session('grupo_error'))
        <div style="padding:.75rem 1rem;border-radius:.5rem;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
            {{ session('grupo_error') }}
        </div>
    @endif

    <p style="font-size:.9rem;color:#4b5563;">
        Cada sucursal es una empresa con su propia configuración, productos, empleados y plan.
        Pulsa <strong>Entrar</strong> para trabajar en ella y <strong>Volver a mi empresa</strong> cuando termines.
        Para crear otra sucursal, pídelo al administrador del sistema.
    </p>

    <x-filament::section>
        @include('partials.tabla-mis-sucursales', ['sucursales' => $sucursales])
    </x-filament::section>
</x-filament-panels::page>
