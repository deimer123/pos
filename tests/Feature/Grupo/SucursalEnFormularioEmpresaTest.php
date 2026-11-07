<?php

use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\ListEmpresas;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 3 del grupo de empresas: el super_admin liga una empresa a la del
// dueno desde el formulario ("Es sucursal de"). Cada sucursal sigue siendo
// una empresa normal, con su propio correo, plan y configuracion.

function superAdminGrupoTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

function empresaConRolGrupoTest(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online'], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

function datosNuevaEmpresaGrupoTest(string $email, array $extra = []): array
{
    return array_merge([
        'name' => 'Sucursal ' . $email,
        'email' => $email,
        'password' => 'password123',
        'passwordConfirmation' => 'password123',
    ], $extra);
}

test('el selector solo se ve en edicion online', function () {
    Livewire::actingAs(superAdminGrupoTest())
        ->test(CreateEmpresa::class)
        ->assertFormFieldIsVisible('empresa_matriz_id')
        ->set('data.tipo_edicion', 'hibrida')
        ->assertFormFieldIsHidden('empresa_matriz_id')
        ->set('data.tipo_edicion', 'online')
        ->assertFormFieldIsVisible('empresa_matriz_id');
});

test('crear una empresa como sucursal la liga a la matriz', function () {
    $matriz = empresaConRolGrupoTest();

    Livewire::actingAs(superAdminGrupoTest())
        ->test(CreateEmpresa::class)
        ->fillForm(datosNuevaEmpresaGrupoTest('norte@example.com', ['empresa_matriz_id' => $matriz->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $norte = User::where('email', 'norte@example.com')->firstOrFail();

    expect($norte->empresa_matriz_id)->toBe($matriz->id)
        ->and($norte->tipo_usuario)->toBe('empresa')
        ->and($norte->hasRole('admin_empresa'))->toBeTrue()
        ->and($norte->id)->not->toBe($matriz->id);
});

test('crear una empresa sin elegir matriz la deja independiente', function () {
    Livewire::actingAs(superAdminGrupoTest())
        ->test(CreateEmpresa::class)
        ->fillForm(datosNuevaEmpresaGrupoTest('suelta@example.com'))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'suelta@example.com')->firstOrFail()->empresa_matriz_id)->toBeNull();
});

test('no se puede crear una sucursal de una empresa que ya es sucursal', function () {
    $matriz = empresaConRolGrupoTest();
    $norte = empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);

    Livewire::actingAs(superAdminGrupoTest())
        ->test(CreateEmpresa::class)
        ->fillForm(datosNuevaEmpresaGrupoTest('cadena@example.com', ['empresa_matriz_id' => $norte->id]))
        ->call('create');

    expect(User::where('email', 'cadena@example.com')->exists())->toBeFalse();
});

test('las opciones de matriz excluyen sucursales, otras ediciones y la propia empresa', function () {
    $matriz = empresaConRolGrupoTest();
    $sucursal = empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $hibrida = empresaConRolGrupoTest(['tipo_edicion' => 'hibrida']);
    $libre = empresaConRolGrupoTest();

    $opciones = app(\App\Services\Grupo\GrupoEmpresas::class);

    $paraNueva = array_keys($opciones->opcionesMatriz());
    $paraLibre = array_keys($opciones->opcionesMatriz($libre));

    expect($paraNueva)->toContain($matriz->id, $libre->id)
        ->and($paraNueva)->not->toContain($sucursal->id, $hibrida->id)
        ->and($paraLibre)->toContain($matriz->id)
        ->and($paraLibre)->not->toContain($libre->id);
});

test('editar una empresa para ligarla a una matriz', function () {
    $matriz = empresaConRolGrupoTest();
    $empresa = empresaConRolGrupoTest();

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->fillForm(['empresa_matriz_id' => $matriz->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($empresa->fresh()->empresa_matriz_id)->toBe($matriz->id);
});

test('editar una sucursal para dejarla independiente', function () {
    $matriz = empresaConRolGrupoTest();
    $sucursal = empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $sucursal->getRouteKey()])
        ->fillForm(['empresa_matriz_id' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sucursal->fresh()->empresa_matriz_id)->toBeNull();
});

test('una empresa con sucursales no puede ligarse a otra ni salir de online', function () {
    $matriz = empresaConRolGrupoTest();
    empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $otra = empresaConRolGrupoTest();

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $matriz->getRouteKey()])
        ->fillForm(['empresa_matriz_id' => $otra->id])
        ->call('save');

    expect($matriz->fresh()->empresa_matriz_id)->toBeNull();

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $matriz->getRouteKey()])
        ->fillForm(['tipo_edicion' => 'hibrida'])
        ->call('save');

    expect($matriz->fresh()->tipo_edicion)->toBe('online');
});

test('una sucursal no puede pasarse a otra edicion mientras siga ligada', function () {
    $matriz = empresaConRolGrupoTest();
    $sucursal = empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $sucursal->getRouteKey()])
        ->fillForm(['tipo_edicion' => 'hibrida'])
        ->call('save');

    $sucursal->refresh();

    expect($sucursal->tipo_edicion)->toBe('online')
        ->and($sucursal->empresa_matriz_id)->toBe($matriz->id);
});

test('guardar una sucursal sin tocar nada conserva su matriz', function () {
    $matriz = empresaConRolGrupoTest();
    $sucursal = empresaConRolGrupoTest(['empresa_matriz_id' => $matriz->id]);

    Livewire::actingAs(superAdminGrupoTest())
        ->test(EditEmpresa::class, ['record' => $sucursal->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sucursal->fresh()->empresa_matriz_id)->toBe($matriz->id);
});

test('la lista muestra de quien es sucursal cada empresa', function () {
    $matriz = empresaConRolGrupoTest(['name' => 'Panaderia Dueña']);
    $sucursal = empresaConRolGrupoTest(['name' => 'Panaderia Norte', 'empresa_matriz_id' => $matriz->id]);

    Livewire::actingAs(superAdminGrupoTest())
        ->test(ListEmpresas::class)
        ->assertCanSeeTableRecords([$matriz, $sucursal])
        ->assertTableColumnStateSet('matriz.name', 'Panaderia Dueña', record: $sucursal);
});
