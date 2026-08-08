<?php

use App\Filament\Resources\ConfiguracionEmpresaResource\Pages\CreateConfiguracionEmpresa;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Flujo feliz de los dos tipos de negocio nuevos (Drogueria y Servicio
// Tecnico de Celulares), mismo patron que
// ConfiguracionEmpresaTipoNegocioTest::"crear la configuracion con un
// tipo_negocio elegido si funciona".

test('crear la configuracion con tipo_negocio drogueria no fuerza ningun modulo especial', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    Livewire::actingAs($empresa)
        ->test(CreateConfiguracionEmpresa::class)
        ->fillForm([
            'nombre_empresa' => 'Droguería de prueba',
            'representante_legal' => 'Rep Legal',
            'nit' => '900123457',
            'tipo_negocio' => 'drogueria',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    expect($config->tipo_negocio)->toBe('drogueria');
    expect($config->usa_taller)->toBeFalsy();
    expect($config->usa_hotel)->toBeFalsy();
    expect($config->usa_servicio_tecnico)->toBeFalsy();
});

test('crear la configuracion con tipo_negocio servicio_tecnico activa usa_servicio_tecnico', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    Livewire::actingAs($empresa)
        ->test(CreateConfiguracionEmpresa::class)
        ->fillForm([
            'nombre_empresa' => 'Servicio Técnico de prueba',
            'representante_legal' => 'Rep Legal',
            'nit' => '900123458',
            'tipo_negocio' => 'servicio_tecnico',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    expect($config->tipo_negocio)->toBe('servicio_tecnico');
    expect($config->usa_servicio_tecnico)->toBeTrue();
    expect($config->usa_taller)->toBeFalsy();
    expect($config->usa_hotel)->toBeFalsy();
    expect($config->usa_mesas)->toBeFalsy();
});
