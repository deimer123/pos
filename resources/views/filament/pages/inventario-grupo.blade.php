<x-filament-panels::page>
    <p style="font-size:.9rem;color:#4b5563;">
        Cantidad en existencia y precio de venta de cada producto en cada empresa del grupo, <strong>solo para consultar</strong>.
        Los productos se emparejan por nombre exacto. Un guion (—) significa que esa empresa no tiene el producto.
    </p>

    <x-filament::input.wrapper>
        <x-filament::input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar producto..." />
    </x-filament::input.wrapper>

    <x-filament::section>
        <div style="overflow-x:auto;">
            @include('partials.tabla-inventario-grupo', ['empresas' => $empresas, 'propia' => $propia, 'filas' => $filas, 'verStock' => $verStock])
        </div>

        <div style="margin-top:1rem;">
            <x-filament::pagination :paginator="$filas" />
        </div>
    </x-filament::section>
</x-filament-panels::page>
