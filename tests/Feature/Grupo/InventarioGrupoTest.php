<?php

use App\Filament\Pages\InventarioGrupo;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 5 del grupo de empresas: inventario del grupo, solo lectura.

function empresaInventarioGrupoTest(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online', 'activo' => true], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

function productoGrupoTest(User $empresa, int $codigo, string $nombre, int $existencias): Product
{
    return Product::create([
        'id_producto' => $codigo,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => $nombre,
        'existencias' => $existencias,
    ]);
}

test('solo lo ve el admin de una empresa que pertenece a un grupo', function () {
    $matriz = empresaInventarioGrupoTest();
    $norte = empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $suelta = empresaInventarioGrupoTest();

    $this->actingAs($matriz);
    expect(InventarioGrupo::canAccess())->toBeTrue();

    $this->actingAs($norte);
    expect(InventarioGrupo::canAccess())->toBeTrue();

    $this->actingAs($suelta);
    expect(InventarioGrupo::canAccess())->toBeFalse();
});

test('un empleado no lo ve', function () {
    $matriz = empresaInventarioGrupoTest();
    empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $matriz->id]);

    $this->actingAs($empleado);

    expect(InventarioGrupo::canAccess())->toBeFalse();
});

test('empareja por nombre y muestra las existencias de cada empresa', function () {
    $matriz = empresaInventarioGrupoTest(['name' => 'Panaderia Dueña']);
    $norte = empresaInventarioGrupoTest(['name' => 'Panaderia Norte', 'empresa_matriz_id' => $matriz->id]);

    productoGrupoTest($matriz, 1, 'Pan x12', 40);
    productoGrupoTest($norte, 7, 'Pan x12', 15);
    productoGrupoTest($matriz, 2, 'Solo en la matriz', 9);

    $pagina = Livewire::actingAs($matriz)->test(InventarioGrupo::class);
    $filas = $pagina->instance()->filas($pagina->instance()->empresasDelGrupo())->items();

    $pan = collect($filas)->firstWhere('nombre', 'Pan x12');
    $solo = collect($filas)->firstWhere('nombre', 'Solo en la matriz');

    // Cada empresa nueva trae un PRODUCTO DE PRUEBA (UserObserver): se ignora.
    expect(collect($filas)->pluck('nombre')->all())->toContain('Pan x12', 'Solo en la matriz')
        ->and((int) $pan->{'existencias_' . $matriz->id})->toBe(40)
        ->and((int) $pan->{'existencias_' . $norte->id})->toBe(15)
        ->and((int) $solo->{'existencias_' . $matriz->id})->toBe(9)
        ->and($solo->{'existencias_' . $norte->id})->toBeNull();

    $pagina->assertSee('Pan x12')->assertSee('Panaderia Norte');
});

test('un codigo igual con nombre distinto no se mezcla', function () {
    $matriz = empresaInventarioGrupoTest();
    $norte = empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);

    productoGrupoTest($matriz, 1, 'Pan x12', 40);
    productoGrupoTest($norte, 1, 'Gaseosa 400ml', 5);

    $pagina = Livewire::actingAs($matriz)->test(InventarioGrupo::class);

    $nombres = collect($pagina->instance()->filas($pagina->instance()->empresasDelGrupo())->items())->pluck('nombre');

    expect($nombres)->toContain('Pan x12', 'Gaseosa 400ml');
});

test('no muestra productos de empresas fuera del grupo', function () {
    $matriz = empresaInventarioGrupoTest();
    $norte = empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $ajena = empresaInventarioGrupoTest();

    productoGrupoTest($matriz, 1, 'Producto del grupo', 3);
    productoGrupoTest($ajena, 1, 'Producto secreto ajeno', 99);

    Livewire::actingAs($norte)
        ->test(InventarioGrupo::class)
        ->assertSee('Producto del grupo')
        ->assertDontSee('Producto secreto ajeno');
});

test('la busqueda filtra por nombre', function () {
    $matriz = empresaInventarioGrupoTest();
    empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);

    productoGrupoTest($matriz, 1, 'Pan x12', 1);
    productoGrupoTest($matriz, 2, 'Gaseosa', 1);

    Livewire::actingAs($matriz)
        ->test(InventarioGrupo::class)
        ->set('buscar', 'gaseo')
        ->assertSee('Gaseosa')
        ->assertDontSee('Pan x12');
});

test('la propia empresa aparece primero en las columnas', function () {
    $matriz = empresaInventarioGrupoTest();
    $norte = empresaInventarioGrupoTest(['empresa_matriz_id' => $matriz->id]);

    $this->actingAs($norte);
    $pagina = Livewire::test(InventarioGrupo::class);

    expect(array_key_first($pagina->instance()->empresasDelGrupo()))->toBe($norte->id);
});
