<?php

use App\Models\Actor;
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

    expect((float) $pan($matriz)->existencias)->toBe(120.0)
        ->and((float) $pan($norte)->existencias)->toBe(40.0)
        ->and((float) $pan($sur)->existencias)->toBe(25.0)
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
        ->and((float) Product::where('empresa_id', $matriz->id)->where('descripcion_larga', 'Pan x12')->value('existencias'))->toBe(7.0);
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

test('grupo:demo crea clientes y proveedores en cada empresa, sin mezclarlos', function () {
    [$matriz, $norte] = duenoConSucursalesDemoTest();

    $this->artisan('grupo:demo')->assertSuccessful();

    $cuenta = fn (User $e, string $clase) => Actor::where('empresa_id', $e->id)->where('clasificacion', $clase)->where('email', 'like', '%@demo.test')->count();

    expect($cuenta($matriz, 'cliente'))->toBe(6)
        ->and($cuenta($matriz, 'proveedor'))->toBe(3)
        ->and($cuenta($norte, 'cliente'))->toBe(6)
        ->and($cuenta($norte, 'proveedor'))->toBe(3)
        ->and(Actor::where('empresa_id', $norte->id)->where('nombre', 'like', 'Cliente exclusivo de ' . $matriz->name)->exists())->toBeFalse();
});

test('grupo:demo no duplica clientes ni proveedores al repetirse', function () {
    duenoConSucursalesDemoTest();

    $this->artisan('grupo:demo')->assertSuccessful();
    $total = Actor::count();

    $this->artisan('grupo:demo')->assertSuccessful();

    expect(Actor::count())->toBe($total);
});
