<?php

use App\Filament\Resources\TrasladoResource;
use App\Filament\Resources\TrasladoResource\Pages\CreateTraslado;
use App\Filament\Resources\TrasladoResource\Pages\ListTraslados;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\Traslado;
use App\Models\User;
use App\Services\Grupo\TrasladoService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 6 del grupo de empresas: traslados de mercancia entre sucursales.

function empresaTrasladoTest(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online', 'activo' => true], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

// [matriz, norte, sur]
function grupoTrasladoTest(): array
{
    $matriz = empresaTrasladoTest(['name' => 'Panaderia Dueña']);
    $norte = empresaTrasladoTest(['name' => 'Panaderia Norte', 'empresa_matriz_id' => $matriz->id]);
    $sur = empresaTrasladoTest(['name' => 'Panaderia Sur', 'empresa_matriz_id' => $matriz->id]);

    return [$matriz, $norte, $sur];
}

function productoTrasladoTest(User $empresa, int $codigo, string $nombre, float $existencias, array $extra = []): Product
{
    return Product::create(array_merge([
        'id_producto' => $codigo,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => $nombre,
        'existencias' => $existencias,
        'precio_costo' => 4000,
        'precio_venta1' => 6000,
        'iva_venta' => 0,
    ], $extra));
}

function stockTrasladoTest(User $empresa, string $nombre): ?float
{
    $valor = Product::where('empresa_id', $empresa->id)->where('descripcion_larga', $nombre)->value('existencias');

    return $valor === null ? null : (float) $valor;
}

function servicioTrasladoTest(): TrasladoService
{
    return app(TrasladoService::class);
}

// ------------------------------------------------------------------ enviar

test('enviar descuenta el stock del origen y deja el traslado en camino', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]], 'Reposición');

    expect($traslado->estado)->toBe('enviado')
        ->and((int) $traslado->origen_id)->toBe($matriz->id)
        ->and((int) $traslado->destino_id)->toBe($norte->id)
        ->and($traslado->items)->toHaveCount(1)
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(70.0)
        ->and(stockTrasladoTest($norte, 'Pan x12'))->toBeNull();

    $kardex = DB::table('kardex')->where('referencia', "Traslado #{$traslado->id}")->first();

    expect($kardex->tipo)->toBe('traslado_salida')
        ->and((int) $kardex->salida)->toBe(30)
        ->and((int) $kardex->stock_anterior)->toBe(100)
        ->and((int) $kardex->stock_actual)->toBe(70);
});

test('no se puede enviar mas de lo que hay y no cambia nada', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);
    productoTrasladoTest($matriz, 2, 'Gaseosa', 50);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte, [
        ['id_producto' => 2, 'cantidad' => 5],
        ['id_producto' => 1, 'cantidad' => 11],
    ]))->toThrow(RuntimeException::class, 'Stock insuficiente');

    expect(stockTrasladoTest($matriz, 'Gaseosa'))->toBe(50.0)
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(10.0)
        ->and(Traslado::count())->toBe(0)
        ->and(DB::table('kardex')->where('tipo', 'traslado_salida')->count())->toBe(0);
});

test('las lineas repetidas del mismo producto se suman antes de validar', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte, [
        ['id_producto' => 1, 'cantidad' => 6],
        ['id_producto' => 1, 'cantidad' => 6],
    ]))->toThrow(RuntimeException::class, 'Stock insuficiente');

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [
        ['id_producto' => 1, 'cantidad' => 4],
        ['id_producto' => 1, 'cantidad' => 6],
    ]);

    expect((float) $traslado->items->first()->cantidad)->toBe(10.0)
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(0.0);
});

test('cantidades invalidas o traslado vacio se rechazan', function (array $lineas) {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte, $lineas))->toThrow(RuntimeException::class);
    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(10.0);
})->with([
    'vacio' => [[]],
    'cero' => [[['id_producto' => 1, 'cantidad' => 0]]],
    'negativa' => [[['id_producto' => 1, 'cantidad' => -3]]],
]);

test('no se trasladan servicios ni productos sin inventario', function (array $extra) {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Servicio', 10, $extra);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'no maneja inventario');
})->with([
    'sin inventario' => [['maneja_inventario' => false]],
    'servicio' => [['tipo_producto' => 'servicio']],
]);

test('un producto de otra empresa no se puede trasladar', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    $ajena = empresaTrasladoTest();
    productoTrasladoTest($ajena, 9, 'Producto ajeno', 20);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 9, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'no existe en tu empresa');

    expect(stockTrasladoTest($ajena, 'Producto ajeno'))->toBe(20.0);
});

// ----------------------------------------------------------- reglas del grupo

test('solo entre empresas del mismo grupo', function () {
    [$matriz] = grupoTrasladoTest();
    $ajena = empresaTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $ajena, [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'mismo grupo');

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $matriz, [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'otra sucursal');
});

test('entre dos sucursales hermanas tambien se puede', function () {
    [, $norte, $sur] = grupoTrasladoTest();
    productoTrasladoTest($norte, 1, 'Pan x12', 10);

    $traslado = servicioTrasladoTest()->enviar($norte, $sur, [['id_producto' => 1, 'cantidad' => 2]]);

    expect($traslado->estado)->toBe('enviado');
});

test('no se traslada con empresas que usan lotes o variantes ni inactivas', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);

    $norte->forceFill(['activo' => false])->save();
    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte->fresh(), [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'activas');
    $norte->forceFill(['activo' => true])->save();

    ConfiguracionEmpresa::create([
        'empresa_id' => $norte->id, 'nombre_empresa' => 'Norte', 'representante_legal' => 'Rep', 'tipo_negocio' => 'tienda',
    ]);
    DB::table('configuracion_empresas')->where('empresa_id', $norte->id)->update(['usa_lotes' => true]);

    expect(fn () => servicioTrasladoTest()->enviar($matriz, $norte->fresh(), [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'lotes o variantes');

    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(10.0);
});

test('solo un administrador puede manejar traslados', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 10);
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $matriz->id]);

    expect(fn () => servicioTrasladoTest()->enviar($empleado, $norte, [['id_producto' => 1, 'cantidad' => 1]]))
        ->toThrow(RuntimeException::class, 'administrador');
});

// ------------------------------------------------------------------ recibir

test('recibir suma al producto que ya existe en el destino (emparejado por nombre)', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);
    productoTrasladoTest($norte, 7, 'Pan x12', 5);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    servicioTrasladoTest()->recibir($norte, $traslado);

    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(70.0)
        ->and(stockTrasladoTest($norte, 'Pan x12'))->toBe(35.0)
        ->and($traslado->fresh()->estado)->toBe('recibido')
        ->and($traslado->fresh()->resuelto_at)->not->toBeNull()
        ->and((int) $traslado->items()->first()->producto_destino_id)->toBe(7);

    $entrada = DB::table('kardex')->where('empresa_id', $norte->id)->where('tipo', 'traslado_entrada')->first();

    expect((int) $entrada->entrada)->toBe(30)
        ->and((int) $entrada->stock_anterior)->toBe(5)
        ->and((int) $entrada->stock_actual)->toBe(35);
});

test('recibir crea el producto en el destino si no lo tenia, con precio y costo', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100, ['precio_costo' => 4200, 'precio_venta1' => 6500, 'iva_venta' => 19]);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    servicioTrasladoTest()->recibir($norte, $traslado);

    $nuevo = Product::where('empresa_id', $norte->id)->where('descripcion_larga', 'Pan x12')->first();

    expect($nuevo)->not->toBeNull()
        ->and((float) $nuevo->existencias)->toBe(30.0)
        ->and((float) $nuevo->precio_costo)->toBe(4200.0)
        ->and((float) $nuevo->precio_venta1)->toBe(6500.0)
        ->and((float) $nuevo->iva_venta)->toBe(19.0)
        ->and($nuevo->maneja_inventario)->toBeTrue()
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(70.0);
});

test('el total del grupo no cambia: lo que sale de uno entra al otro', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);
    productoTrasladoTest($norte, 1, 'Pan x12', 25);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 40.5]]);

    // En camino: la mercancia no cuenta en ninguna de las dos.
    expect(stockTrasladoTest($matriz, 'Pan x12') + stockTrasladoTest($norte, 'Pan x12'))->toBe(84.5);

    servicioTrasladoTest()->recibir($norte, $traslado);

    expect(stockTrasladoTest($matriz, 'Pan x12') + stockTrasladoTest($norte, 'Pan x12'))->toBe(125.0);
});

test('pulsar recibir dos veces no suma el stock dos veces', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    servicioTrasladoTest()->recibir($norte, $traslado);

    expect(fn () => servicioTrasladoTest()->recibir($norte, $traslado))->toThrow(RuntimeException::class, 'ya fue recibido');

    expect(stockTrasladoTest($norte, 'Pan x12'))->toBe(30.0)
        ->and(DB::table('kardex')->where('empresa_id', $norte->id)->where('tipo', 'traslado_entrada')->count())->toBe(1);
});

test('solo el destino puede recibir, y no otra sucursal del grupo', function () {
    [$matriz, $norte, $sur] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);

    foreach ([$matriz, $sur] as $intruso) {
        expect(fn () => servicioTrasladoTest()->recibir($intruso, $traslado))->toThrow(RuntimeException::class, 'destino');
    }

    expect($traslado->fresh()->estado)->toBe('enviado')
        ->and(stockTrasladoTest($norte, 'Pan x12'))->toBeNull();
});

// ------------------------------------------------------ rechazar y cancelar

test('rechazar devuelve la mercancia al origen', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    servicioTrasladoTest()->rechazar($norte, $traslado);

    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(100.0)
        ->and(stockTrasladoTest($norte, 'Pan x12'))->toBeNull()
        ->and($traslado->fresh()->estado)->toBe('rechazado')
        ->and(DB::table('kardex')->where('empresa_id', $matriz->id)->where('tipo', 'traslado_devolucion')->count())->toBe(1);
});

test('cancelar devuelve la mercancia al origen y solo lo hace el origen', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);

    expect(fn () => servicioTrasladoTest()->cancelar($norte, $traslado))->toThrow(RuntimeException::class, 'permiso');
    expect(fn () => servicioTrasladoTest()->rechazar($matriz, $traslado))->toThrow(RuntimeException::class, 'permiso');
    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(70.0);

    servicioTrasladoTest()->cancelar($matriz, $traslado);

    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(100.0)
        ->and($traslado->fresh()->estado)->toBe('cancelado');
});

test('un traslado ya resuelto no se puede resolver otra vez', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    servicioTrasladoTest()->recibir($norte, $traslado);

    expect(fn () => servicioTrasladoTest()->cancelar($matriz, $traslado))->toThrow(RuntimeException::class, 'ya fue');
    expect(fn () => servicioTrasladoTest()->rechazar($norte, $traslado))->toThrow(RuntimeException::class, 'ya fue');

    expect(stockTrasladoTest($matriz, 'Pan x12'))->toBe(70.0)
        ->and(stockTrasladoTest($norte, 'Pan x12'))->toBe(30.0);
});

test('si el producto ya no existe en el origen no se devuelve en silencio', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    $producto = productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);
    $producto->delete();

    expect(fn () => servicioTrasladoTest()->cancelar($matriz, $traslado))->toThrow(RuntimeException::class, 'ya no existe');
    expect($traslado->fresh()->estado)->toBe('enviado');
});

// -------------------------------------------------------------- pantalla

test('la pantalla de traslados solo la ve el admin de una empresa con grupo', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    $suelta = empresaTrasladoTest();
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $matriz->id]);

    $this->actingAs($matriz);
    expect(TrasladoResource::canAccess())->toBeTrue();

    $this->actingAs($norte);
    expect(TrasladoResource::canAccess())->toBeTrue();

    foreach ([$suelta, $empleado] as $usuario) {
        $this->actingAs($usuario);
        expect(TrasladoResource::canAccess())->toBeFalse();
    }
});

test('cada empresa ve solo los traslados donde participa', function () {
    [$matriz, $norte, $sur] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);
    productoTrasladoTest($norte, 1, 'Pan x12', 100);

    $conNorte = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 1]]);
    $entreNorteYSur = servicioTrasladoTest()->enviar($norte, $sur, [['id_producto' => 1, 'cantidad' => 1]]);

    // La matriz participa en el primero pero no en el segundo.
    Livewire::actingAs($matriz)
        ->test(ListTraslados::class)
        ->assertCanSeeTableRecords([$conNorte])
        ->assertCanNotSeeTableRecords([$entreNorteYSur]);
});

test('el destino recibe desde la pantalla y la mercancia entra a su inventario', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);
    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);

    Livewire::actingAs($norte)
        ->test(ListTraslados::class)
        ->callTableAction('recibir', $traslado)
        ->assertHasNoTableActionErrors();

    expect(stockTrasladoTest($norte, 'Pan x12'))->toBe(30.0)
        ->and($traslado->fresh()->estado)->toBe('recibido');
});

test('el origen no ve el boton recibir pero si cancelar', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);
    $traslado = servicioTrasladoTest()->enviar($matriz, $norte, [['id_producto' => 1, 'cantidad' => 30]]);

    Livewire::actingAs($matriz)
        ->test(ListTraslados::class)
        ->assertTableActionHidden('recibir', $traslado)
        ->assertTableActionHidden('rechazar', $traslado)
        ->assertTableActionVisible('cancelar', $traslado);
});

test('crear un traslado desde el formulario lo envia y descuenta el stock', function () {
    [$matriz, $norte] = grupoTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    Livewire::actingAs($matriz)
        ->test(CreateTraslado::class)
        ->fillForm([
            'destino_id' => $norte->id,
            'observacion' => 'Reposición semanal',
            'items' => [['id_producto' => 1, 'cantidad' => 12]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $traslado = Traslado::first();

    expect($traslado)->not->toBeNull()
        ->and($traslado->estado)->toBe('enviado')
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(88.0);
});

test('no se puede enviar a una empresa fuera del grupo ni manipulando el formulario', function () {
    [$matriz, $norte, $sur] = grupoTrasladoTest();
    $ajena = empresaTrasladoTest();
    productoTrasladoTest($matriz, 1, 'Pan x12', 100);

    Livewire::actingAs($matriz)
        ->test(CreateTraslado::class)
        ->fillForm([
            'destino_id' => $ajena->id,
            'items' => [['id_producto' => 1, 'cantidad' => 1]],
        ])
        ->call('create');

    // La autoridad es el servicio: aunque el formulario deje pasar el valor,
    // no se crea el traslado ni se toca el stock.
    expect(Traslado::count())->toBe(0)
        ->and(stockTrasladoTest($matriz, 'Pan x12'))->toBe(100.0);
});
