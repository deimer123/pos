<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Turion (base de datos local, SQLite) es solo para el punto de venta --
// no debe cargar nada de /admin. Se simula "estar en Turion" con un mock
// parcial de DB::getDriverName() (la suite corre contra MySQL real, pero
// esta es la unica llamada que el middleware hace para decidir si aplica).

function simularTurion(): void
{
    DB::partialMock()->shouldReceive('getDriverName')->andReturn('sqlite');
}

test('en el droplet (mysql) /admin no se bloquea para nadie', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)->get('/admin/plans')->assertOk();
});

test('en Turion, un cajero que intenta entrar a /admin se redirige al POS', function () {
    Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
    $cajero = User::factory()->create(['tipo_usuario' => 'empleado']);
    $cajero->assignRole('cajero');

    simularTurion();

    $this->actingAs($cajero)->get('/admin/plans')->assertRedirect(route('pos'));
});

test('en Turion, un super_admin sin destino en el POS recibe un error claro en vez de un loop', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');

    simularTurion();

    $this->actingAs($superAdmin)->get('/admin/plans')->assertForbidden();
});

test('en Turion, /admin/login sigue siendo accesible (es la unica puerta de autenticacion)', function () {
    simularTurion();

    $this->get('/admin/login')->assertOk();
});
