<?php

use App\Filament\Resources\ConfiguracionEmpresaResource\Pages\EditConfiguracionEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// tipo_edicion (online/hibrida/local) controla como funciona el sistema
// para cada empresa: Online es el default (solo droplet), Hibrida agrega
// los botones de Turion, y Local bloquea el login normal al droplet (esa
// cuenta solo existe como ancla para emitirle licencias -- su interfaz
// real es la app de escritorio activada con codigo).

function crearSuperAdminEdicionTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

function crearAdminEmpresaConConfiguracion(string $tipoEdicion): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'tipo_edicion' => $tipoEdicion,
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

test('tipo_edicion por defecto queda en online al crear una empresa', function () {
    $superAdmin = crearSuperAdminEdicionTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Online',
            'email' => 'empresaonline@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresaonline@example.com')->first();

    expect($empresa->tipo_edicion)->toBe('online');
    expect($empresa->puedeUsarHibrida())->toBeFalse();
    expect($empresa->esClienteLocal())->toBeFalse();
    expect($empresa->puedeIngresarPorPlan())->toBeTrue();
});

test('el super_admin puede elegir hibrida al crear una empresa', function () {
    $superAdmin = crearSuperAdminEdicionTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Hibrida',
            'email' => 'empresahibrida@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'tipo_edicion' => 'hibrida',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresahibrida@example.com')->first();

    expect($empresa->tipo_edicion)->toBe('hibrida');
    expect($empresa->puedeUsarHibrida())->toBeTrue();
    expect($empresa->puedeIngresarPorPlan())->toBeTrue();
});

test('elegir local bloquea el ingreso normal al droplet para esa empresa', function () {
    $superAdmin = crearSuperAdminEdicionTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Local',
            'email' => 'empresalocal@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'tipo_edicion' => 'local',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresalocal@example.com')->first();

    expect($empresa->tipo_edicion)->toBe('local');
    expect($empresa->esClienteLocal())->toBeTrue();
    expect($empresa->puedeIngresarPorPlan())->toBeFalse();
});

test('una empresa Local no lleva plan (cobro unico por licencia, no suscripcion)', function () {
    $superAdmin = crearSuperAdminEdicionTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa Local Sin Plan',
            'email' => 'empresalocalsinplan@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'tipo_edicion' => 'local',
            'plan_id' => null,
        ])
        ->assertFormFieldIsHidden('plan_id')
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresalocalsinplan@example.com')->first();

    expect($empresa->plan_id)->toBeNull();
    expect($empresa->plan_meses)->toBeNull();
    expect($empresa->plan_started_at)->toBeNull();
    expect($empresa->plan_ends_at)->toBeNull();
    expect($empresa->valor_plan_total)->toBeNull();
});

test('online e hibrida si muestran la seccion de plan comercial', function () {
    $superAdmin = crearSuperAdminEdicionTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->assertFormFieldIsVisible('plan_id')
        ->set('data.tipo_edicion', 'hibrida')
        ->assertFormFieldIsVisible('plan_id');
});

test('un empleado de una empresa Local tambien queda bloqueado (hereda de empresaPrincipal)', function () {
    Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'local']);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $empleado->assignRole('cajero');

    expect($empleado->esClienteLocal())->toBeTrue();
    expect($empleado->puedeIngresarPorPlan())->toBeFalse();
});

test('sin hibrida, la empresa no ve los botones de Turion en su configuracion', function () {
    $empresa = crearAdminEmpresaConConfiguracion('online');
    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    Livewire::actingAs($empresa)
        ->test(EditConfiguracionEmpresa::class, ['record' => $config->getKey()])
        ->assertActionDoesNotExist('emparejarTurion')
        ->assertActionDoesNotExist('descargarTurion');
});

test('con hibrida, la empresa si ve los botones de Turion', function () {
    $empresa = crearAdminEmpresaConConfiguracion('hibrida');
    $config = ConfiguracionEmpresa::where('empresa_id', $empresa->id)->first();

    Livewire::actingAs($empresa)
        ->test(EditConfiguracionEmpresa::class, ['record' => $config->getKey()])
        ->assertActionExists('emparejarTurion')
        ->assertActionExists('descargarTurion');
});

test('un admin_empresa de tipo Local es expulsado de /admin con un mensaje claro', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'local', 'activo' => true]);
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);
    $empresa->assignRole('admin_empresa');

    $respuesta = $this->actingAs($empresa)->get('/admin');

    $respuesta->assertRedirect(route('filament.admin.auth.login'));
    $respuesta->assertSessionHasErrors('email');
    $this->assertGuest();

    expect(session('errors')->get('email')[0])->toContain('edición Local');
});
