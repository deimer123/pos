<x-filament::page>
    <x-filament::section heading="Datos de facturación" description="Con qué datos aparecerás como emisor en las facturas de plan/servicios que le generes a tus clientes." class="combo-franja-azul">
        <form wire:submit.prevent="guardar" class="space-y-4">
            {{ $this->form }}

            <x-filament::button type="submit" color="primary" icon="heroicon-o-check">
                Guardar
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament::page>
