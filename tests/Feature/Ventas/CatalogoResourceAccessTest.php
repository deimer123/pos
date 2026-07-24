<?php

use App\Filament\Resources\CatalogoResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

// El super_admin administra TODAS las empresas, no le corresponde entrar
// al catalogo publico de una empresa especifica -- ese es un recurso de
// admin_empresa (o digitador con permiso).

test('un super_admin no puede acceder al recurso de Catalogos', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin);

    expect(CatalogoResource::canAccess())->toBeFalse();
});

test('un admin_empresa si puede acceder al recurso de Catalogos', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    $this->actingAs($empresa);

    expect(CatalogoResource::canAccess())->toBeTrue();
});
