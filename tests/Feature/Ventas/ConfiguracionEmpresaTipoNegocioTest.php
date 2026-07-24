<?php

use App\Filament\Resources\ConfiguracionEmpresaResource\Pages\CreateConfiguracionEmpresa;
use App\Models\User;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Regresion del bug reportado por el usuario: al crear una empresa nueva,
// "Tipo de negocio" quedaba guardado en "Tienda / Almacen" sin que el
// admin_empresa lo hubiera elegido. Causa: un <select> nativo del
// navegador SIEMPRE muestra visualmente la primera opcion como si
// estuviera marcada (aunque el estado real en Livewire sea null), y al
// navegar el wizard ese valor terminaba sincronizandose y guardandose --
// un problema conocido de Livewire con <select> nativos. El fix es
// native(false), que usa el selector propio de Filament (nunca
// preselecciona nada visualmente). No se puede probar el renderizado
// real del navegador sin un test de browser (Dusk), asi que esta prueba
// verifica directamente que el componente no sea nativo.

test('el select de tipo_negocio no es un <select> nativo del navegador', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    $instance = Livewire::actingAs($empresa)
        ->test(CreateConfiguracionEmpresa::class)
        ->instance();

    $tipoNegocioField = collect($instance->form->getFlatFields())
        ->first(fn ($field) => $field->getName() === 'tipo_negocio');

    expect($tipoNegocioField)->toBeInstanceOf(Select::class);
    expect($tipoNegocioField->isNative())->toBeFalse();
});
