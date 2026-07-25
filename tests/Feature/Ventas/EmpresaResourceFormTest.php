<?php

use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Los campos de credenciales Factus (todo menos el NIT, que se usa
// siempre) solo tienen sentido si se activo la facturacion electronica --
// deben estar ocultos mientras el toggle este apagado, y aparecer en
// cuanto se prenda.

function crearSuperAdminParaFormTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

test('los campos de Factus estan ocultos hasta que se activa facturacion electronica', function () {
    $superAdmin = crearSuperAdminParaFormTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        // El NIT siempre esta visible (se usa aunque no se active Factus).
        ->assertFormFieldIsVisible('factus.nit')
        ->assertFormFieldIsHidden('factus.factus_environment')
        ->set('data.factus.factus_enabled', true)
        ->assertFormFieldIsVisible('factus.factus_environment');
});

test('la seccion Plan de activacion ya no se muestra visible (campos ocultos, autocompletados)', function () {
    $superAdmin = crearSuperAdminParaFormTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->assertDontSee('Plan de activacion')
        ->assertDontSee('Fecha de activacion');
});
