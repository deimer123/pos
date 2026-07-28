<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\OrdenMesaItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Turion\OrdenMesaSyncPull;
use Laravel\Sanctum\Sanctum;

// OrdenMesaSyncPull::fusionar() es el lado de Turion que recibe la lista de
// ordenes de mesa activas del droplet (PairingController::ordenesMesa()) y
// la mezcla con lo que ya existe localmente. Cubre TANTO mesas de salon
// como domicilios (un domicilio es una OrdenMesa con tipo_pedido=
// 'domicilio') -- ninguna de las dos bajaba antes de esto: Turion mostraba
// las mesas en verde/libre aunque estuvieran ocupadas en el droplet, y los
// domicilios activos alla nunca aparecian aqui.

function crearEmpresaMesaPullTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa mesa pull test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

function crearMesaPullTest(User $empresa, string $codigo = 'M1'): Mesa
{
    return Mesa::create([
        'empresa_id' => $empresa->id, 'codigo' => $codigo, 'nombre' => "Mesa $codigo", 'estado' => 'libre',
    ]);
}

function crearProductoMesaPullTest(User $empresa, int $idProducto = 5001): Product
{
    return Product::create([
        'id_producto' => $idProducto, 'empresa_id' => $empresa->id, 'id_familia1' => 1, 'id_familia2' => 1,
        'descripcion_larga' => 'Producto mesa test', 'precio_venta1' => 10000, 'existencias' => 100,
    ]);
}

test('una orden de mesa remota nueva se crea localmente con sus items', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa);
    $producto = crearProductoMesaPullTest($empresa);

    OrdenMesaSyncPull::fusionar([
        [
            'mesa_id' => $mesa->id,
            'cliente_id' => null,
            'estado' => 'en_preparacion',
            'subtotal' => 10000, 'impuestos' => 0, 'descuento' => 0, 'total' => 10000,
            'observaciones' => null, 'tipo_pedido' => 'mesa', 'costo_empaque' => 0,
            'dom_nombre' => null, 'dom_telefono' => null, 'dom_direccion' => null, 'dom_observaciones' => null,
            'dom_costo_domicilio' => 0, 'dom_costo_desechables' => 0,
            'numero_cocina_dia' => 1, 'entregada' => false,
            'items' => [
                ['product_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => 10000, 'subtotal' => 10000, 'estado_cocina' => 'enviado', 'nota_cocina' => null],
            ],
        ],
    ], $empresa->id);

    $orden = OrdenMesa::where('empresa_id', $empresa->id)->where('mesa_id', $mesa->id)->first();

    expect($orden)->not->toBeNull();
    expect($orden->estado)->toBe('en_preparacion');
    expect($orden->items)->toHaveCount(1);
    expect((float) $orden->items->first()->subtotal)->toBe(10000.0);
    expect($mesa->fresh()->estado)->toBe('ocupada');
});

test('un domicilio remoto (tipo_pedido=domicilio) baja igual que cualquier orden de mesa', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa, 'DOMV-1');
    $producto = crearProductoMesaPullTest($empresa);

    OrdenMesaSyncPull::fusionar([
        [
            'mesa_id' => $mesa->id,
            'cliente_id' => null,
            'estado' => 'en_preparacion',
            'subtotal' => 20000, 'impuestos' => 0, 'descuento' => 0, 'total' => 20000,
            'observaciones' => null, 'tipo_pedido' => 'domicilio', 'costo_empaque' => 3000,
            'dom_nombre' => 'Pepito Perez', 'dom_telefono' => '3001234567', 'dom_direccion' => 'Calle 29 # 12-05',
            'dom_observaciones' => null, 'dom_costo_domicilio' => 3000, 'dom_costo_desechables' => 0,
            'numero_cocina_dia' => 1, 'entregada' => false,
            'items' => [
                ['product_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 10000, 'subtotal' => 20000, 'estado_cocina' => 'enviado', 'nota_cocina' => null],
            ],
        ],
    ], $empresa->id);

    $orden = OrdenMesa::where('empresa_id', $empresa->id)->where('mesa_id', $mesa->id)->first();

    expect($orden->tipo_pedido)->toBe('domicilio');
    expect($orden->dom_nombre)->toBe('Pepito Perez');
    expect($orden->dom_direccion)->toBe('Calle 29 # 12-05');
});

test('una orden de mesa remota ya bajada antes se actualiza, no se duplica', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa);

    $payload = fn (float $total) => [[
        'mesa_id' => $mesa->id, 'cliente_id' => null, 'estado' => 'en_preparacion',
        'subtotal' => $total, 'impuestos' => 0, 'descuento' => 0, 'total' => $total,
        'observaciones' => null, 'tipo_pedido' => 'mesa', 'costo_empaque' => 0,
        'dom_nombre' => null, 'dom_telefono' => null, 'dom_direccion' => null, 'dom_observaciones' => null,
        'dom_costo_domicilio' => 0, 'dom_costo_desechables' => 0,
        'numero_cocina_dia' => 1, 'entregada' => false, 'items' => [],
    ]];

    OrdenMesaSyncPull::fusionar($payload(10000), $empresa->id);
    OrdenMesaSyncPull::fusionar($payload(25000), $empresa->id);

    expect(OrdenMesa::where('empresa_id', $empresa->id)->where('mesa_id', $mesa->id)->count())->toBe(1);
    expect((float) OrdenMesa::where('empresa_id', $empresa->id)->where('mesa_id', $mesa->id)->first()->total)->toBe(25000.0);
});

test('una orden de mesa local activa se limpia si la mesa ya no aparece activa en el droplet', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa);

    $orden = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'en_preparacion']);
    OrdenMesaItem::create([
        'empresa_id' => $empresa->id, 'orden_mesa_id' => $orden->id,
        'product_id' => crearProductoMesaPullTest($empresa)->id,
        'cantidad' => 1, 'precio_unitario' => 5000, 'subtotal' => 5000,
    ]);

    OrdenMesaSyncPull::fusionar([], $empresa->id);

    expect(OrdenMesa::find($orden->id))->toBeNull();
});

test('PairingController::ordenesMesa() solo devuelve las activas, con sus items', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa);
    $producto = crearProductoMesaPullTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'en_preparacion']);
    $activa->items()->create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'cantidad' => 1, 'precio_unitario' => 10000, 'subtotal' => 10000,
    ]);

    $otraMesa = crearMesaPullTest($empresa, 'M2');
    OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $otraMesa->id, 'estado' => 'facturada']);

    $respuesta = $this->getJson('/api/pairing/ordenes-mesa')->assertOk();
    $ordenes = $respuesta->json('ordenes');

    expect($ordenes)->toHaveCount(1);
    expect($ordenes[0]['mesa_id'])->toBe($mesa->id);
    expect($ordenes[0]['items'])->toHaveCount(1);
});

test('PosSyncController::mesaActualizar() marca la orden activa de esa mesa como domicilio', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $orden = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'abierta']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/actualizar', [
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'mesa_id' => $mesa->id,
        'tipo_pedido' => 'domicilio',
        'dom_nombre' => 'Lucas Dangos',
        'dom_telefono' => '3009998877',
        'dom_direccion' => 'Calle 12 # 15-78',
        'dom_costo_domicilio' => 5000,
    ]);

    $respuesta->assertOk();

    $orden->refresh();
    expect($orden->estado)->toBe('en_preparacion');
    expect($orden->tipo_pedido)->toBe('domicilio');
    expect($orden->dom_nombre)->toBe('Lucas Dangos');
    expect((float) $orden->dom_costo_domicilio)->toBe(5000.0);
});

test('PosSyncController::mesaLiberar() cancela la orden activa y libera la mesa', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa, 'M3');
    $mesa->update(['estado' => 'ocupada']);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $orden = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'en_preparacion']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/liberar', [
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'mesa_id' => $mesa->id,
    ]);

    $respuesta->assertOk();
    expect($orden->fresh()->estado)->toBe('cancelada');
    expect($mesa->fresh()->estado)->toBe('libre');
});

test('PosSyncController::mesaLiberar() NO libera la mesa si queda una cuenta en espera', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa, 'M4');
    $mesa->update(['estado' => 'ocupada']);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'en_preparacion']);
    $enEspera = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'lista']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/liberar', [
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'mesa_id' => $mesa->id,
    ]);

    $respuesta->assertOk();
    expect($activa->fresh()->estado)->toBe('cancelada');
    expect($enEspera->fresh()->estado)->toBe('lista');
    expect($mesa->fresh()->estado)->toBe('ocupada');
});

test('PosSyncController::mesaEnEspera() pone la orden activa en lista y libera la mesa fisica', function () {
    $empresa = crearEmpresaMesaPullTest();
    $mesa = crearMesaPullTest($empresa, 'M5');
    $mesa->update(['estado' => 'ocupada']);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $orden = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'en_preparacion']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/en-espera', [
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'mesa_id' => $mesa->id,
    ]);

    $respuesta->assertOk();
    expect($orden->fresh()->estado)->toBe('lista');
    expect($mesa->fresh()->estado)->toBe('libre');
});
