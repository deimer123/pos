<?php

use App\Filament\Resources\EmpleadoResource\Pages\CreateEmpleado;
use App\Filament\Resources\EmpleadoResource\Pages\EditEmpleado;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// max_vendedores/max_cajeros/max_digitadores no se piden al crear una
// empresa Local (el formulario oculta esa seccion, ver EmpresaResource --
// el tope ahi lo pone la cantidad de licencias de terminal, no un numero
// por rol) -- la columna se queda en su default de la migracion
// (max_vendedores/max_cajeros = 1, max_digitadores = 0), un numero que no
// tiene relacion con cuantas licencias compro la empresa. Antes,
// CreateEmpleado/EditEmpleado::validateRoleLimits() comparaba "actuales >=
// limite" sin excepcion, asi que una empresa Local quedaba atascada para
// siempre en como mucho 1 vendedor/1 cajero/0 digitadores, sin importar
// cuantos terminales tuviera activados.

function crearEmpresaConEdicion(string $tipoEdicion, int $maxVendedores = 1): User
{
    // EmpleadoResource (tabla, badges, descripciones del CheckboxList de
    // roles) consulta hasRole() para varios roles a la vez sin importar
    // cuales se usen en el test puntual -- hacen falta todos para que
    // Spatie no tire RoleDoesNotExist al renderizar.
    foreach (['admin_empresa', 'vendedor', 'cajero', 'digitador', 'mesero', 'cocina', 'taller', 'recepcion'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }

    $empresa = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'tipo_edicion' => $tipoEdicion,
        'max_vendedores' => $maxVendedores,
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

test('una empresa Local puede pasar el limite (default) de vendedores sin que la rechace', function () {
    // max_vendedores queda en 1 (el default de la migracion, la empresa
    // Local nunca lo configura) -- el segundo vendedor ya superaria ese
    // numero. Si el bug siguiera ahi, este test fallaria en el segundo.
    $empresa = crearEmpresaConEdicion('local', maxVendedores: 1);

    Livewire::actingAs($empresa)
        ->test(CreateEmpleado::class)
        ->fillForm([
            'name' => 'Primer Vendedor',
            'email' => 'primer-vendedor-local@example.test',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'roles' => ['vendedor'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::actingAs($empresa)
        ->test(CreateEmpleado::class)
        ->fillForm([
            'name' => 'Segundo Vendedor',
            'email' => 'segundo-vendedor-local@example.test',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'roles' => ['vendedor'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'primer-vendedor-local@example.test')->exists())->toBeTrue();
    expect(User::where('email', 'segundo-vendedor-local@example.test')->exists())->toBeTrue();
});

test('una empresa online SI sigue respetando su limite configurado de vendedores', function () {
    $empresa = crearEmpresaConEdicion('online', maxVendedores: 1);

    // El primero entra dentro del limite (1).
    Livewire::actingAs($empresa)
        ->test(CreateEmpleado::class)
        ->fillForm([
            'name' => 'Primer Vendedor',
            'email' => 'primer-vendedor-online@example.test',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'roles' => ['vendedor'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // El segundo ya supera el limite de 1 -- debe rechazarse.
    Livewire::actingAs($empresa)
        ->test(CreateEmpleado::class)
        ->fillForm([
            'name' => 'Segundo Vendedor',
            'email' => 'segundo-vendedor-online@example.test',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'roles' => ['vendedor'],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect(User::where('email', 'segundo-vendedor-online@example.test')->exists())->toBeFalse();
});

test('editar un empleado de una empresa Local para sumarle un rol tambien ignora el limite', function () {
    // max_cajeros queda en 1 (default) -- se llena ese cupo con un cajero
    // ya existente, para que agregarle el rol a un SEGUNDO empleado sea lo
    // que de verdad prueba que el limite se ignora en Local.
    $empresa = crearEmpresaConEdicion('local');

    $primerCajero = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $primerCajero->assignRole('cajero');

    // EmpleadoResource::getEloquentQuery() solo resuelve empleados que YA
    // tengan alguno de los roles del modulo -- un empleado recien creado
    // sin ningun rol no seria alcanzable por esta pantalla en la app real
    // (CreateEmpleado siempre le asigna al menos uno), asi que el caso
    // realista a probar es sumarle un SEGUNDO rol a uno que ya tenia uno.
    $empleado = User::factory()->create([
        'tipo_usuario' => 'empleado',
        'empresa_id' => $empresa->id,
    ]);
    $empleado->assignRole('vendedor');

    Livewire::actingAs($empresa)
        ->test(EditEmpleado::class, ['record' => $empleado->getKey()])
        ->fillForm(['roles' => ['vendedor', 'cajero']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($empleado->fresh()->hasRole('cajero'))->toBeTrue();
});
