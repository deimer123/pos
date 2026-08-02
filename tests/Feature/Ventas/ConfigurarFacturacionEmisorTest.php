<?php

use App\Filament\Pages\ConfigurarFacturacionEmisor;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Pagina Filament (solo super_admin) para configurar sus propios datos de
// facturacion como "empresa emisora" -- ver App\Filament\Pages\
// ConfigurarFacturacionEmisor y App\Services\Ventas\FacturarPlanService.
// A diferencia de ConfiguracionEmpresaResource, NO pide tipo_negocio ni
// nada relacionado a ventas: el super_admin solo factura servicios de
// droplet/Local, no opera un negocio real.

function crearSuperAdminConfigurarFacturacionTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

test('canAccess() es solo para super_admin', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');
    $this->actingAs($empresa);

    expect(ConfigurarFacturacionEmisor::canAccess())->toBeFalse();
});

test('el formulario no pide tipo_negocio ni campos de venta', function () {
    $superAdmin = crearSuperAdminConfigurarFacturacionTest();

    Livewire::actingAs($superAdmin)
        ->test(ConfigurarFacturacionEmisor::class)
        ->assertFormFieldDoesNotExist('tipo_negocio')
        ->assertFormFieldDoesNotExist('usa_mesas')
        ->assertFormFieldDoesNotExist('usa_hotel')
        ->assertFormFieldDoesNotExist('usa_taller');
});

test('super_admin puede guardar sus datos de facturacion por primera vez', function () {
    $superAdmin = crearSuperAdminConfigurarFacturacionTest();

    Livewire::actingAs($superAdmin)
        ->test(ConfigurarFacturacionEmisor::class)
        ->fillForm([
            'nombre_empresa' => 'Mi Empresa SAS',
            'representante_legal' => 'Yo Mismo',
            'nit' => '900123456-7',
            'telefono' => '3001234567',
            'direccion' => 'Calle 1 # 2-3',
        ])
        ->call('guardar')
        ->assertHasNoFormErrors();

    $config = ConfiguracionEmpresa::where('empresa_id', $superAdmin->id)->first();

    expect($config)->not->toBeNull();
    expect($config->nombre_empresa)->toBe('Mi Empresa SAS');
    expect($config->nit)->toBe('900123456-7');
    expect($config->tipo_negocio)->toBeNull();
});

test('guardar una segunda vez actualiza la misma fila en vez de duplicarla', function () {
    $superAdmin = crearSuperAdminConfigurarFacturacionTest();

    ConfiguracionEmpresa::create([
        'empresa_id' => $superAdmin->id,
        'nombre_empresa' => 'Nombre Viejo',
        'representante_legal' => 'Rep Viejo',
        'nit' => '111',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(ConfigurarFacturacionEmisor::class)
        ->fillForm([
            'nombre_empresa' => 'Nombre Nuevo',
            'representante_legal' => 'Rep Nuevo',
            'nit' => '222',
        ])
        ->call('guardar')
        ->assertHasNoFormErrors();

    expect(ConfiguracionEmpresa::where('empresa_id', $superAdmin->id)->count())->toBe(1);
    expect(ConfiguracionEmpresa::where('empresa_id', $superAdmin->id)->first()->nombre_empresa)->toBe('Nombre Nuevo');
});
