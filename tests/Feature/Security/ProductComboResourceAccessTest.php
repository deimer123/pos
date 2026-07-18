<?php

use App\Filament\Resources\ProductComboResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Regresion de la vulnerabilidad corregida: ProductComboResource solo
// definia shouldRegisterNavigation() (oculta el menu) pero no canAccess()/
// canViewAny(), asi que un digitador con "combos" oculto por el admin
// igual podia entrar por URL directa a /admin/product-combos.

beforeEach(function () {
    Role::create(['name' => 'admin_empresa']);
    Role::create(['name' => 'digitador']);
});

test('un digitador con combos oculto no puede acceder al resource', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $digitador = User::factory()->create([
        'tipo_usuario' => 'empleado',
        'empresa_id' => $empresa->id,
        'recursos_ocultos_admin' => ['combos'],
    ]);
    $digitador->assignRole('digitador');

    $this->actingAs($digitador);

    expect(ProductComboResource::canAccess())->toBeFalse();
});

test('un digitador sin restriccion si puede acceder al resource', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $digitador = User::factory()->create([
        'tipo_usuario' => 'empleado',
        'empresa_id' => $empresa->id,
        'recursos_ocultos_admin' => [],
    ]);
    $digitador->assignRole('digitador');

    $this->actingAs($digitador);

    expect(ProductComboResource::canAccess())->toBeTrue();
});

test('admin_empresa siempre puede acceder al resource sin importar restricciones', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    $this->actingAs($empresa);

    expect(ProductComboResource::canAccess())->toBeTrue();
});
