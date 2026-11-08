<x-filament-widgets::widget>
    <x-filament::section heading="Mis sucursales" icon="heroicon-o-building-storefront"
        description="Pulsa Entrar para trabajar en una sucursal; arriba tendrás «Volver a mi empresa».">
        @if (session('grupo_error'))
            <div style="padding:.75rem 1rem;border-radius:.5rem;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:.75rem;">
                {{ session('grupo_error') }}
            </div>
        @endif

        @include('partials.tabla-mis-sucursales', ['sucursales' => $sucursales])
    </x-filament::section>
</x-filament-widgets::widget>
