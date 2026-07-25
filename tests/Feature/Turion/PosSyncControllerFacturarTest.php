<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// PosSyncController::venta()/mesaFacturar()/tallerFacturar()/hotelFacturar()
// reciben ahora el set completo de opciones de FacturarVentaService::facturar()
// (antes solo aceptaban carrito+medio_pago y forzaban salida/contado/cajero
// como vendedor) -- esto es lo que llama FacturarEnLineaService cuando
// Turion factura en linea, asi que necesita la MISMA funcionalidad que
// facturar directo en el droplet: cliente, factura electronica, credito,
// domicilio, propina, vendedor manual. Se prueba aqui via HTTP porque es
// la puerta de entrada real (ruta + validacion + auth:sanctum), no solo el
// servicio que ya cubre FacturarVentaServiceComisionesTest.

function crearEmpresaSyncTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa sync test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'permite_stock_negativo' => true,
    ]);

    return $empresa;
}

function crearProductoSyncTest(User $empresa, int $idProducto = 2001, float $precio = 30000): Product
{
    return Product::create([
        'id_producto' => $idProducto,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto sync test',
        'precio_venta1' => $precio,
        'existencias' => 100,
    ]);
}

test('venta() con el payload minimo de siempre sigue funcionando igual (salida, contado, cajero)', function () {
    $empresa = crearEmpresaSyncTest();
    crearProductoSyncTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/venta', [
        'uuid' => (string) Str::uuid(),
        'carrito' => [
            ['id_producto' => 2001, 'nombre' => 'Producto sync test', 'precio' => 30000, 'cantidad' => 1, 'descuento' => 0],
        ],
        'medio_pago' => 'efectivo',
    ]);

    $respuesta->assertOk();
    $factura = Factura::findOrFail($respuesta->json('id'));

    expect($factura->tipo_factura)->toBe('salida');
    expect($factura->tipo_pago)->toBe('contado');
    expect($factura->vendedor_id)->toBe($cajero->id);
    expect((float) $factura->total)->toBe(30000.0);
});

test('venta() en linea puede facturar a un cliente especifico, con propina y domicilio', function () {
    $empresa = crearEmpresaSyncTest();
    crearProductoSyncTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 9001,
        'tipo' => 2,
        'tipo_documento_id' => 6,
        'nombre' => 'Cliente con domicilio',
    ]);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/venta', [
        'uuid' => (string) Str::uuid(),
        'carrito' => [
            ['id_producto' => 2001, 'nombre' => 'Producto sync test', 'precio' => 30000, 'cantidad' => 1, 'descuento' => 0],
        ],
        'medio_pago' => 'efectivo',
        'cliente_id' => $cliente->id,
        'tipo_pedido' => 'domicilio',
        'costo_empaque' => 5000,
        'dom_costo_domicilio' => 5000,
        'dom_direccion' => 'Calle 1 # 2-3',
        'propina_monto' => 3000,
    ]);

    $respuesta->assertOk();
    $factura = Factura::findOrFail($respuesta->json('id'));

    expect($factura->cliente_id)->toBe($cliente->id);
    expect($factura->tipo_pedido)->toBe('domicilio');
    expect((float) $factura->dom_costo_domicilio)->toBe(5000.0);
    expect((float) $factura->propina_sugerida)->toBe(3000.0);
    // La propina es solo sugerencia: no se suma al total ($30000 producto + $5000 domicilio).
    expect((float) $factura->total)->toBe(35000.0);
});

test('venta() en linea con credito valida el cupo disponible en el servidor, no en el cliente', function () {
    $empresa = crearEmpresaSyncTest();
    crearProductoSyncTest($empresa, precio: 30000);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 9002,
        'tipo' => 2,
        'tipo_documento_id' => 6,
        'nombre' => 'Cliente con poco cupo',
        'permite_credito' => true,
        'dias_credito' => 30,
        'limite_credito' => 10000, // menor al total de la venta (30000)
    ]);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/venta', [
        'uuid' => (string) Str::uuid(),
        'carrito' => [
            ['id_producto' => 2001, 'nombre' => 'Producto sync test', 'precio' => 30000, 'cantidad' => 1, 'descuento' => 0],
        ],
        'cliente_id' => $cliente->id,
        'tipo_pago' => 'credito',
    ]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('message'))->toContain('Cupo insuficiente');
});

test('venta() es idempotente por uuid: reintentar no crea una segunda factura', function () {
    $empresa = crearEmpresaSyncTest();
    crearProductoSyncTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'carrito' => [
            ['id_producto' => 2001, 'nombre' => 'Producto sync test', 'precio' => 30000, 'cantidad' => 1, 'descuento' => 0],
        ],
        'medio_pago' => 'efectivo',
    ];

    $primera = $this->postJson('/api/pairing/subir/venta', $payload)->assertOk();
    $segunda = $this->postJson('/api/pairing/subir/venta', $payload)->assertOk();

    expect($segunda->json('id'))->toBe($primera->json('id'));
    expect(Factura::where('empresa_id', $empresa->id)->count())->toBe(1);
});
