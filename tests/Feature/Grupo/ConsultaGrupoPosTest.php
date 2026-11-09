<?php

use App\Livewire\ConsultaGrupoPos;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Consulta de solo lectura de otras sucursales dentro del POS.

function empresaPosGrupoTest(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online', 'activo' => true], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

function cajeroPosGrupoTest(User $empresa): User
{
    Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);

    $cajero = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $cajero->assignRole('cajero');

    return $cajero;
}

function productoPosGrupoTest(User $empresa, string $nombre, int $existencias, int $precio): void
{
    Product::create([
        'id_producto' => 5001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => $nombre,
        'existencias' => $existencias,
        'precio_venta1' => $precio,
    ]);
}

function configPosGrupoTest(User $empresa, bool $verStock): void
{
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Panaderia',
        'representante_legal' => 'Rep',
        'tipo_negocio' => 'tienda',
    ]);
    DB::table('configuracion_empresas')->where('empresa_id', $empresa->id)->update(['permite_ver_stock_no_admin' => $verStock]);
}

test('el cajero ve cantidad y precio de las otras sucursales', function () {
    $matriz = empresaPosGrupoTest(['name' => 'Panaderia Dueña']);
    $norte = empresaPosGrupoTest(['name' => 'Panaderia Norte', 'empresa_matriz_id' => $matriz->id]);
    configPosGrupoTest($norte, true);
    $cajero = cajeroPosGrupoTest($norte);

    productoPosGrupoTest($matriz, 'Pan especial', 40, 6000);
    productoPosGrupoTest($norte, 'Pan especial', 15, 6500);

    Livewire::actingAs($cajero)
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->assertSee('Pan especial')
        ->assertSee('Panaderia Dueña')
        ->assertSee('Cantidad')
        ->assertSee('$ 6.000')
        ->assertSee('$ 6.500');
});

test('si el cajero no puede ver stock, solo ve precios', function () {
    $matriz = empresaPosGrupoTest();
    $norte = empresaPosGrupoTest(['empresa_matriz_id' => $matriz->id]);
    configPosGrupoTest($norte, false);
    $cajero = cajeroPosGrupoTest($norte);

    productoPosGrupoTest($matriz, 'Pan especial', 4321, 6000);

    Livewire::actingAs($cajero)
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->assertSee('$ 6.000')
        ->assertDontSee('Cantidad')
        ->assertDontSee('4.321');
});

test('no muestra productos de empresas fuera del grupo', function () {
    $matriz = empresaPosGrupoTest();
    $norte = empresaPosGrupoTest(['empresa_matriz_id' => $matriz->id]);
    configPosGrupoTest($norte, true);
    $ajena = empresaPosGrupoTest();

    productoPosGrupoTest($ajena, 'Producto secreto ajeno', 99, 1000);

    Livewire::actingAs(cajeroPosGrupoTest($norte))
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->assertDontSee('Producto secreto ajeno');
});

test('una empresa sin grupo no puede abrir la consulta', function () {
    $suelta = empresaPosGrupoTest();
    configPosGrupoTest($suelta, true);

    Livewire::actingAs(cajeroPosGrupoTest($suelta))
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->assertForbidden();
});

test('el componente es solo de lectura: no expone acciones de escritura', function () {
    $metodos = collect((new ReflectionClass(ConsultaGrupoPos::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn ($m) => $m->class === ConsultaGrupoPos::class)
        ->map(fn ($m) => $m->name)
        ->sort()->values()->all();

    expect($metodos)->toBe(['abrir', 'cerrar', 'render']);
});

test('abrir la consulta no cambia ni un producto', function () {
    $matriz = empresaPosGrupoTest();
    $norte = empresaPosGrupoTest(['empresa_matriz_id' => $matriz->id]);
    configPosGrupoTest($norte, true);
    productoPosGrupoTest($matriz, 'Pan especial', 40, 6000);

    $antes = DB::table('products')->orderBy('id')->get()->toJson();

    Livewire::actingAs(cajeroPosGrupoTest($norte))
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->set('buscar', 'Pan');

    expect(DB::table('products')->orderBy('id')->get()->toJson())->toBe($antes);
});

test('el modal usa el formato de los demas modales del POS', function () {
    $matriz = empresaPosGrupoTest();
    $norte = empresaPosGrupoTest(['empresa_matriz_id' => $matriz->id]);
    configPosGrupoTest($norte, true);

    Livewire::actingAs(cajeroPosGrupoTest($norte))
        ->test(ConsultaGrupoPos::class)
        ->call('abrir')
        ->assertSeeHtml('class="pos-pro"')
        ->assertSeeHtml('linear-gradient(180deg,#2563eb 0%,#4f46e5 100%)')
        ->assertSee('Cerrar');
});
