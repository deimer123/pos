<?php

use App\Filament\Resources\ConfiguracionEmpresaResource\Pages\EditConfiguracionEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// puede_usar_hibrida controla si una empresa tiene habilitada la edicion
// Hibrida (Turion): antes de esto, cualquier admin_empresa veia los
// botones "Emparejar equipo offline"/"Descargar Sistema POS Offline" sin
// que el super_admin hubiera decidido activarle esa edicion.

function crearSuperAdminHibridaTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    // CreateEmpresa::afterCreate() asigna 'admin_empresa' a toda empresa
    // nueva -- tiene que existir el rol antes de crear una desde el test.
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

function crearAdminEmpresaConConfiguracion(bool $puedeUsarHibrida): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'puede_usar_hibrida' => $puedeUsarHibrida,
    ]);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

test('puede_usar_hibrida por defecto queda en false al crear una empresa', function () {
    $superAdmin = crearSuperAdminHibridaTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Nueva',
            'email' => 'empresanueva@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresanueva@example.com')->first();

    expect($empresa->puede_usar_hibrida)->toBeFalse();
});

test('el super_admin puede activar puede_usar_hibrida al crear una empresa', function () {
    $superAdmin = crearSuperAdminHibridaTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Hibrida',
            'email' => 'empresahibrida@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'puede_usar_hibrida' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresahibrida@example.com')->first();

    expect($empresa->puede_usar_hibrida)->toBeTrue();
});

test('sin puede_usar_hibrida, la empresa no ve los botones de Turion en su configuracion', function () {
    $empresa = crearAdminEmpresaConConfiguracion(puedeUsarHibrida: false);
    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    Livewire::actingAs($empresa)
        ->test(EditConfiguracionEmpresa::class, ['record' => $config->getKey()])
        ->assertActionDoesNotExist('emparejarTurion')
        ->assertActionDoesNotExist('descargarTurion');
});

test('con puede_usar_hibrida activo, la empresa si ve los botones de Turion', function () {
    $empresa = crearAdminEmpresaConConfiguracion(puedeUsarHibrida: true);
    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    Livewire::actingAs($empresa)
        ->test(EditConfiguracionEmpresa::class, ['record' => $config->getKey()])
        ->assertActionExists('emparejarTurion')
        ->assertActionExists('descargarTurion');
});
