<form wire:submit.prevent="importar" class="space-y-6">

    {{-- Empresa --}}
    <div>
        <label for="empresa_id" class="block font-medium text-sm text-gray-700">Empresa</label>
        <select wire:model="empresa_id" id="empresa_id" class="form-select w-full rounded-md shadow-sm border-gray-300">
            <option value="">Seleccione una empresa</option>
            @foreach($empresas as $empresa)
                <option value="{{ $empresa['id'] }}">{{ $empresa['name'] }}</option>
            @endforeach
        </select>
        @error('empresa_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </div>

    {{-- Archivo de Familias --}}
<div>
    <label for="archivo_familias" class="block font-medium text-sm text-gray-700">Archivo de Familias</label>
    <input type="file" wire:model="archivo_familias" id="archivo_familias" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md" />
    @error('archivo_familias') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
</div>

{{-- Archivo de Subfamilias --}}
<div>
    <label for="archivo_subfamilias" class="block font-medium text-sm text-gray-700">Archivo de Subfamilias</label>
    <input type="file" wire:model="archivo_subfamilias" id="archivo_subfamilias" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md" />
    @error('archivo_subfamilias') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
</div>

{{-- Archivo de Actores --}}
<div>
    <label for="archivo_actores" class="block font-medium text-sm text-gray-700">Archivo de Actores</label>
    <input type="file" wire:model="archivo_actores" id="archivo_actores" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md" />
    @error('archivo_actores') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
</div>

{{-- Archivo de Productos --}}
<div>
    <label for="archivo_productos" class="block font-medium text-sm text-gray-700">Archivo de Productos</label>
    <input type="file" wire:model="archivo_productos" id="archivo_productos" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md" />
    @error('archivo_productos') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
</div>

{{-- Archivo de Códigos Alternos --}}
<div>
    <label for="archivo_codigos_alternos" class="block font-medium text-sm text-gray-700">Archivo de Códigos Alternos</label>
    <input type="file" wire:model="archivo_codigos_alternos" id="archivo_codigos_alternos" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md" />
    @error('archivo_codigos_alternos') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
</div>

    <div>
        <x-filament::button type="submit" color="primary">
            Importar Datos
        </x-filament::button>
    </div>

</form>
