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

// Guardia dura en el servidor: sin importar como haya llegado vacio
// (bug del select nativo, o cualquier otra causa futura), crear la
// configuracion con tipo_negocio en blanco debe rechazarse -- nunca
// guardarse en silencio con un valor por defecto.

test('crear la configuracion con tipo_negocio vacio se rechaza con un error de validacion', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    Livewire::actingAs($empresa)
        ->test(CreateConfiguracionEmpresa::class)
        ->fillForm([
            'nombre_empresa' => 'Empresa de prueba',
            'representante_legal' => 'Rep Legal',
            'tipo_negocio' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['tipo_negocio']);

    expect(\App\Models\ConfiguracionEmpresa::where('empresa_id', $empresa->id)->exists())->toBeFalse();
});

test('crear la configuracion con un tipo_negocio elegido si funciona', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    Livewire::actingAs($empresa)
        ->test(CreateConfiguracionEmpresa::class)
        ->fillForm([
            'nombre_empresa' => 'Empresa de prueba',
            'representante_legal' => 'Rep Legal',
            'nit' => '900123456',
            'tipo_negocio' => 'taller',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(\App\Models\ConfiguracionEmpresa::where('empresa_id', $empresa->id)->value('tipo_negocio'))->toBe('taller');
});

// Reproduce la causa raiz real reportada por el usuario (varias empresas
// seguidas quedaban con "Tienda / Almacen" sin elegirlo, incluso con los
// fixes anteriores del wizard): EmpresaResource::saveFactusConfig() crea
// la fila de configuracion_empresas al crear la empresa (para el NIT/
// Factus), mucho antes de que el admin_empresa vea el wizard, y nunca
// incluye 'tipo_negocio' -- si la columna tuviera un default a nivel de
// base de datos, quedaria relleno solo. Debe quedar en null.

test('crear una empresa desde EmpresaResource no deja tipo_negocio predefinido', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'name' => 'SURTIMOTOS']);

    \App\Filament\Resources\EmpresaResource::saveFactusConfig($empresa, []);

    $config = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    expect($config)->not->toBeNull();
    expect($config->tipo_negocio)->toBeNull();
    expect($empresa->fresh()->necesitaConfiguracionInicial())->toBeTrue();
});
