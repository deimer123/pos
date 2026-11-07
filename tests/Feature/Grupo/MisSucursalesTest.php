<?php

use App\Filament\Pages\MisSucursales;
use App\Models\User;
use App\Services\Grupo\CambioDeEmpresa;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 4 del grupo de empresas: el dueno ve sus sucursales y entra a cada
// una (inicio de sesion real de esa empresa) y vuelve a la suya.

function empresaAdminPaso4(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge([
        'tipo_usuario' => 'empresa',
        'tipo_edicion' => 'online',
        'activo' => true,
    ], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

function duenoConSucursalPaso4(): array
{
    $matriz = empresaAdminPaso4(['name' => 'Panaderia Dueña']);
    $norte = empresaAdminPaso4(['name' => 'Panaderia Norte', 'empresa_matriz_id' => $matriz->id]);

    return [$matriz, $norte];
}

// ------------------------------------------------------------------ entrar

test('el dueño entra a su sucursal y queda trabajando como esa empresa', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();

    $this->actingAs($matriz)
        ->post(route('grupo.entrar', $norte))
        ->assertRedirect(route('eleccion'))
        ->assertSessionHas(CambioDeEmpresa::SESSION_ORIGEN, $matriz->id);

    $this->assertAuthenticatedAs($norte);

    expect(auth()->user()->getEmpresaActualId())->toBe($norte->id)
        ->and($norte->fresh()->session_token)->not->toBeNull()
        ->and($norte->fresh()->session_id)->not->toBeNull()
        ->and(session('session_token'))->toBe($norte->fresh()->session_token);
});

test('no puede entrar a la sucursal de otro dueño', function () {
    [$matriz] = duenoConSucursalPaso4();
    [, $ajena] = duenoConSucursalPaso4();

    $this->actingAs($matriz)
        ->post(route('grupo.entrar', $ajena))
        ->assertSessionHas('grupo_error')
        ->assertSessionMissing(CambioDeEmpresa::SESSION_ORIGEN);

    $this->assertAuthenticatedAs($matriz);
});

test('no puede entrar a una empresa independiente', function () {
    [$matriz] = duenoConSucursalPaso4();
    $suelta = empresaAdminPaso4();

    $this->actingAs($matriz)
        ->post(route('grupo.entrar', $suelta))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($matriz);
});

test('un empleado no puede entrar a las sucursales', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $matriz->id]);

    $this->actingAs($empleado)
        ->post(route('grupo.entrar', $norte))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($empleado);
});

test('no entra a una sucursal inactiva o con plan vencido', function (array $cambio) {
    [$matriz, $norte] = duenoConSucursalPaso4();
    $norte->forceFill($cambio)->save();

    $this->actingAs($matriz)
        ->post(route('grupo.entrar', $norte))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($matriz);
})->with([
    'inactiva' => [['activo' => false]],
    'plan vencido' => [['plan_ends_at' => '2020-01-01']],
]);

test('no entra a una sucursal sin administrador', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();
    $norte->removeRole('admin_empresa');

    $this->actingAs($matriz)
        ->post(route('grupo.entrar', $norte))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($matriz);
});

test('estando dentro de una sucursal no se puede entrar a otra', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();
    $sur = empresaAdminPaso4(['empresa_matriz_id' => $matriz->id]);

    $this->actingAs($norte)
        ->withSession([CambioDeEmpresa::SESSION_ORIGEN => $matriz->id])
        ->post(route('grupo.entrar', $sur))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($norte);
});

// ------------------------------------------------------------------ volver

test('desde la sucursal se vuelve a la empresa del dueño', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();

    $this->actingAs($norte)
        ->withSession([CambioDeEmpresa::SESSION_ORIGEN => $matriz->id])
        ->post(route('grupo.volver'))
        ->assertRedirect(route('eleccion'))
        ->assertSessionMissing(CambioDeEmpresa::SESSION_ORIGEN);

    $this->assertAuthenticatedAs($matriz);

    expect($matriz->fresh()->session_token)->not->toBeNull()
        ->and(auth()->user()->getEmpresaActualId())->toBe($matriz->id);
});

test('volver sin haber entrado desde una matriz no hace nada', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();

    $this->actingAs($norte)
        ->post(route('grupo.volver'))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($norte);
});

test('volver solo funciona hacia la matriz de la sucursal actual', function () {
    [, $norte] = duenoConSucursalPaso4();
    $otra = empresaAdminPaso4();

    $this->actingAs($norte)
        ->withSession([CambioDeEmpresa::SESSION_ORIGEN => $otra->id])
        ->post(route('grupo.volver'))
        ->assertSessionHas('grupo_error');

    $this->assertAuthenticatedAs($norte);
});

// ----------------------------------------------------------------- pantalla

test('la pantalla Mis sucursales la ve solo la matriz que tiene sucursales', function () {
    [$matriz, $norte] = duenoConSucursalPaso4();
    $independiente = empresaAdminPaso4();

    $this->actingAs($matriz);
    expect(MisSucursales::canAccess())->toBeTrue();

    $this->actingAs($independiente);
    expect(MisSucursales::canAccess())->toBeFalse();

    $this->actingAs($norte);
    expect(MisSucursales::canAccess())->toBeFalse();
});

test('estando dentro de una sucursal no se ve la pantalla del dueño', function () {
    [$matriz] = duenoConSucursalPaso4();

    $this->actingAs($matriz)->withSession([CambioDeEmpresa::SESSION_ORIGEN => $matriz->id]);

    expect(MisSucursales::canAccess())->toBeFalse();
});

test('la pantalla lista las sucursales del dueño y no las de otros', function () {
    [$matriz] = duenoConSucursalPaso4();
    [, $ajena] = duenoConSucursalPaso4();

    Livewire::actingAs($matriz)
        ->test(MisSucursales::class)
        ->assertSee('Panaderia Norte')
        ->assertDontSee($ajena->email);
});
