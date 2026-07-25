<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Prefactura;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// El seed compartido de tests/Pest.php solo inserta el tipo de documento
// NIT (id 6, que usan las empresas) -- el cliente nuevo que se crea aqui
// por find-or-create necesita ademas Cedula de ciudadania (id 3, el
// default de PosSyncController::resolverClientePrefactura() para una
// persona natural sin tipo de documento explicito).
beforeEach(function () {
    DB::table('tipos_documento')->insertOrIgnore(['id' => 3, 'nombre' => 'Cédula de ciudadanía']);
});

// PosSyncController::prefacturaGuardar() es el lado del droplet que recibe
// la foto completa de una prefactura que Turion crea/edita offline (ver
// CarritoVenta::encolarPrefacturaSiEsLocal()). No existia sincronizacion de
// prefacturas antes de esta fase -- facturarlas dos veces (una vez subida
// al droplet, otra vez desde Turion) es justo lo que este endpoint, junto
// con PosSyncController::venta(), tiene que evitar.

function crearEmpresaPrefacturaTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa prefactura test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'permite_stock_negativo' => true,
    ]);

    return $empresa;
}

function crearProductoPrefacturaTest(User $empresa, int $idProducto = 3001, float $precio = 20000): Product
{
    return Product::create([
        'id_producto' => $idProducto,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto prefactura test',
        'precio_venta1' => $precio,
        'existencias' => 100,
    ]);
}

function payloadPrefacturaTest(array $overrides = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'observaciones' => 'Nota de prueba',
        'estado' => 'borrador',
        'items' => [
            ['producto_id' => 3001, 'nombre' => 'Producto prefactura test', 'cantidad' => 2, 'precio' => 20000, 'descuento' => 0],
        ],
    ], $overrides);
}

test('prefacturaGuardar() sin servidor_id crea una prefactura nueva y resuelve el cliente por nombre', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest([
        'cliente' => ['nombre' => 'Cliente Nuevo Offline', 'identificacion' => '123456'],
    ]));

    $respuesta->assertOk();
    expect($respuesta->json('ya_facturada'))->toBeFalse();

    $prefactura = Prefactura::with('productos')->findOrFail($respuesta->json('id'));
    expect($prefactura->empresa_id)->toBe($empresa->id);
    expect($prefactura->observaciones)->toBe('Nota de prueba');
    expect($prefactura->productos)->toHaveCount(1);
    expect((float) $prefactura->productos->first()->subtotal)->toBe(40000.0);

    $cliente = Actor::findOrFail($prefactura->cliente_id);
    expect($cliente->nombre)->toBe('Cliente Nuevo Offline');
    expect($cliente->identificacion)->toBe('123456');
});

test('prefacturaGuardar() con servidor_id actualiza la misma prefactura y reemplaza sus productos', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $primera = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest())->assertOk();
    $servidorId = $primera->json('id');

    $segunda = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest([
        'servidor_id' => $servidorId,
        'observaciones' => 'Nota editada sin conexion',
        'items' => [
            ['producto_id' => 3001, 'nombre' => 'Producto prefactura test', 'cantidad' => 5, 'precio' => 20000, 'descuento' => 0],
        ],
    ]))->assertOk();

    expect($segunda->json('id'))->toBe($servidorId);
    expect(Prefactura::where('empresa_id', $empresa->id)->count())->toBe(1);

    $prefactura = Prefactura::with('productos')->findOrFail($servidorId);
    expect($prefactura->observaciones)->toBe('Nota editada sin conexion');
    expect($prefactura->productos)->toHaveCount(1);
    expect((float) $prefactura->productos->first()->cantidad)->toBe(5.0);
});

test('prefacturaGuardar() no revive una prefactura ya facturada/borrada en el droplet', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $primera = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest())->assertOk();
    $servidorId = $primera->json('id');

    // Alguien la factura/borra directo en el droplet mientras tanto.
    Prefactura::find($servidorId)->delete();

    $segunda = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest([
        'servidor_id' => $servidorId,
        'observaciones' => 'Edicion tardia offline',
    ]))->assertOk();

    expect($segunda->json('ya_facturada'))->toBeTrue();
    expect($segunda->json('id'))->toBeNull();
    expect(Prefactura::where('empresa_id', $empresa->id)->count())->toBe(0);
});

test('prefacturaGuardar() es idempotente por uuid', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $payload = payloadPrefacturaTest();

    $primera = $this->postJson('/api/pairing/subir/prefactura/guardar', $payload)->assertOk();
    $segunda = $this->postJson('/api/pairing/subir/prefactura/guardar', $payload)->assertOk();

    expect($segunda->json('id'))->toBe($primera->json('id'));
    expect(Prefactura::where('empresa_id', $empresa->id)->count())->toBe(1);
});

test('venta() con prefactura_servidor_id factura y borra la prefactura en el droplet', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $prefacturaRespuesta = $this->postJson('/api/pairing/subir/prefactura/guardar', payloadPrefacturaTest())->assertOk();
    $servidorId = $prefacturaRespuesta->json('id');

    $ventaRespuesta = $this->postJson('/api/pairing/subir/venta', [
        'uuid' => (string) Str::uuid(),
        'carrito' => [
            ['id_producto' => 3001, 'nombre' => 'Producto prefactura test', 'precio' => 20000, 'cantidad' => 2, 'descuento' => 0],
        ],
        'medio_pago' => 'efectivo',
        'prefactura_servidor_id' => $servidorId,
    ]);

    $ventaRespuesta->assertOk();
    Factura::findOrFail($ventaRespuesta->json('id'));
    expect(Prefactura::find($servidorId))->toBeNull();
});

test('venta() rechaza facturar una prefactura que ya no existe en el droplet', function () {
    $empresa = crearEmpresaPrefacturaTest();
    crearProductoPrefacturaTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/venta', [
        'uuid' => (string) Str::uuid(),
        'carrito' => [
            ['id_producto' => 3001, 'nombre' => 'Producto prefactura test', 'precio' => 20000, 'cantidad' => 1, 'descuento' => 0],
        ],
        'medio_pago' => 'efectivo',
        'prefactura_servidor_id' => 999999,
    ]);

    $respuesta->assertStatus(409);
    expect($respuesta->json('message'))->toContain('ya fue facturada o eliminada');
    expect(Factura::where('empresa_id', $empresa->id)->count())->toBe(0);
});
