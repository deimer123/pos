<?php

use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

function duenoConSucursalesDemoTest(): array
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $matriz = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online']);
    $norte = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online', 'empresa_matriz_id' => $matriz->id]);
    $sur = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online', 'empresa_matriz_id' => $matriz->id]);

    return [$matriz, $norte, $sur];
}

test('grupo:demo crea productos en la matriz y en cada sucursal', function () {
    [$matriz, $norte, $sur] = duenoConSucursalesDemoTest();

    $this->artisan('grupo:demo')->assertSuccessful();

    $pan = fn (User $e) => Product::where('empresa_id', $e->id)->where('descripcion_larga', 'Pan x12')->first();

    expect($pan($matriz)->existencias)->toBe(120)
        ->and($pan($norte)->existencias)->toBe(40)
        ->and($pan($sur)->existencias)->toBe(25)
        ->and(Product::where('empresa_id', $matriz->id)->where('descripcion_larga', 'Producto solo matriz')->exists())->toBeTrue()
        ->and(Product::where('empresa_id', $norte->id)->where('descripcion_larga', 'Producto solo matriz')->exists())->toBeFalse();
});

test('grupo:demo es idempotente y no cambia el stock de lo que ya existe', function () {
    [$matriz] = duenoConSucursalesDemoTest();

    $this->artisan('grupo:demo')->assertSuccessful();

    Product::where('empresa_id', $matriz->id)->where('descripcion_larga', 'Pan x12')->update(['existencias' => 7]);
    $total = Product::count();

    $this->artisan('grupo:demo')->assertSuccessful();

    expect(Product::count())->toBe($total)
        ->and(Product::where('empresa_id', $matriz->id)->where('descripcion_larga', 'Pan x12')->value('existencias'))->toBe(7);
});

test('grupo:demo no toca empresas fuera del grupo', function () {
    duenoConSucursalesDemoTest();
    $ajena = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online']);
    $antes = Product::where('empresa_id', $ajena->id)->count();

    $this->artisan('grupo:demo')->assertSuccessful();

    expect(Product::where('empresa_id', $ajena->id)->count())->toBe($antes);
});

test('grupo:demo se niega a correr en produccion', function () {
    [$matriz] = duenoConSucursalesDemoTest();
    $antes = Product::count();

    app()->detectEnvironment(fn () => 'production');

    $this->artisan('grupo:demo')->assertFailed();

    expect(Product::count())->toBe($antes);
});

test('grupo:demo avisa si no hay sucursales', function () {
    User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online']);

    $this->artisan('grupo:demo')->assertFailed();
});
