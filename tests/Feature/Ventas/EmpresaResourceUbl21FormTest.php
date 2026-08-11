<?php

use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Mismo criterio que EmpresaResourceFormTest.php (Factus): los campos de
// credenciales del proveedor alterno solo tienen sentido si esta
// seleccionado como proveedor activo -- deben estar ocultos mientras el
// selector diga "factus" (el default), y aparecer al cambiarlo a "ubl21".

function crearSuperAdminParaUbl21FormTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

test('los campos del proveedor alterno estan ocultos hasta elegirlo como proveedor activo', function () {
    $superAdmin = crearSuperAdminParaUbl21FormTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->assertFormFieldIsVisible('ubl21.factura_electronica_proveedor')
        ->assertFormFieldIsHidden('ubl21.ubl21_base_url')
        ->set('data.ubl21.factura_electronica_proveedor', 'ubl21')
        ->assertFormFieldIsVisible('ubl21.ubl21_base_url')
        ->assertFormFieldIsVisible('ubl21.ubl21_prefix');
});
