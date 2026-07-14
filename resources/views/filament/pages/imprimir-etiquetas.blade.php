<x-filament::page>
    <form wire:submit.prevent="generar" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit" color="primary">
            Generar etiquetas
        </x-filament::button>
    </form>
</x-filament::page>
