<?php

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Base del modulo de sucursales (paso 2): estructura de base de datos y
// modelo. Todo es aditivo: las empresas existentes siguen funcionando igual
// porque usa_sucursales queda apagado y sucursal_id queda en NULL.

test('existen las tablas y la columna nuevas de sucursales', function () {
    expect(Schema::hasTable('sucursales'))->toBeTrue()
        ->and(Schema::hasTable('sucursal_user'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'usa_sucursales'))->toBeTrue();
});

test('sucursal_id es nullable e indexada en la tabla operativa', function (string $tabla) {
    $columna = DB::selectOne(
        'select is_nullable as nullable from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
        [$tabla, 'sucursal_id']
    );

    expect($columna)->not->toBeNull()
        ->and($columna->nullable)->toBe('YES');

    $indice = DB::selectOne(
        'select count(*) as total from information_schema.statistics where table_schema = database() and table_name = ? and column_name = ?',
        [$tabla, 'sucursal_id']
    );

    expect((int) $indice->total)->toBeGreaterThan(0);
})->with([
    'facturas',
    'nota_creditos',
    'prefacturas',
    'devoluciones',
    'compras',
    'devolucion_compras',
    'cajas',
    'gastos',
    'ajustes_inventario',
    'stock_movimientos',
    'kardex',
    'mesas',
    'ordenes_mesas',
    'orden_mesa_items',
]);

test('una empresa nueva tiene el interruptor de sucursales apagado', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    expect($empresa->fresh()->usa_sucursales)->toBeFalse()
        ->and($empresa->fresh()->empresaUsaSucursales())->toBeFalse();
});

test('un empleado hereda el interruptor de sucursales de su empresa', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'usa_sucursales' => true]);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);

    expect($empleado->fresh()->empresaUsaSucursales())->toBeTrue();
});

test('una sucursal pertenece a su empresa y admite usuarios asignados', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'usa_sucursales' => true]);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);

    $sucursal = Sucursal::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Saravena',
        'es_principal' => true,
    ]);

    $sucursal->usuarios()->attach($empleado->id);

    expect($sucursal->empresa->is($empresa))->toBeTrue()
        ->and($empresa->sucursales)->toHaveCount(1)
        ->and($empleado->sucursalesAsignadas)->toHaveCount(1)
        ->and($empleado->sucursalesAsignadas->first()->is($sucursal))->toBeTrue()
        ->and($sucursal->es_principal)->toBeTrue()
        // fresh(): el default (activo=true) lo pone la base de datos, y el
        // modelo recien creado no lo trae hasta que se vuelve a leer.
        ->and($sucursal->fresh()->activo)->toBeTrue();
});

test('el nombre de la sucursal no se repite dentro de la misma empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa', 'usa_sucursales' => true]);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa', 'usa_sucursales' => true]);

    Sucursal::create(['empresa_id' => $empresaA->id, 'nombre' => 'Saravena']);

    expect(fn () => Sucursal::create(['empresa_id' => $empresaA->id, 'nombre' => 'Saravena']))
        ->toThrow(QueryException::class);

    // Otra empresa si puede usar el mismo nombre.
    $otra = Sucursal::create(['empresa_id' => $empresaB->id, 'nombre' => 'Saravena']);

    expect($otra->exists)->toBeTrue();
});

test('un usuario no se asigna dos veces a la misma sucursal', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'usa_sucursales' => true]);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $sucursal = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Arauquita']);

    $sucursal->usuarios()->attach($empleado->id);

    expect(fn () => $sucursal->usuarios()->attach($empleado->id))
        ->toThrow(QueryException::class);
});
