<x-filament::page>
    <form wire:submit.prevent="importar" class="space-y-4" enctype="multipart/form-data">
        <x-filament::input type="file" wire:model="archivo" label="Archivo Excel (.xlsx)" />

        <x-filament::button type="submit" color="primary">
            Importar Subfamilias
        </x-filament::button>
    </form>

    <div class="mt-6">
        <a href="{{ asset('storage/plantillas/plantilla_subfamilias.xlsx') }}" class="text-primary-600 underline">
            📥 Descargar plantilla en blanco
        </a>
    </div>
</x-filament::page>
