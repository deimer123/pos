<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Mecanico;
use App\Models\Product;
use App\Models\User;
use App\Services\Ventas\FacturarVentaService;

// Cubre el motor de comisiones/propina generalizado en
// FacturarVentaService::facturar() (ver ConfiguracionEmpresa::rolComisionActual()):
// comision de vendedor sobre productos normales, universal para cualquier
// tipo de negocio con el toggle activo, y propina de mesero que es solo
// una sugerencia (no se cobra ni se contabiliza).

function crearEmpresaComisionTest(array $config): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create(array_merge([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'permite_stock_negativo' => true,
    ], $config));

    return $empresa;
}

function crearProductoComisionTest(User $empresa, float $precio = 50000): Product
{
    return Product::create([
        'id_producto' => 1001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Camiseta talla M azul',
        'precio_venta1' => $precio,
        'existencias' => 100,
    ]);
}

test('comision de vendedor se calcula sobre una venta de producto normal', function () {
    $empresa = crearEmpresaComisionTest(['tipo_negocio' => 'tienda', 'usa_comision_vendedores' => true]);
    $producto = crearProductoComisionTest($empresa);

    $vendedor = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Mecanico::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Vendedor de prueba',
        'rol' => Mecanico::ROL_VENDEDOR,
        'user_id' => $vendedor->id,
        'porcentaje_comision' => 10,
        'activo' => true,
    ]);

    $factura = app(FacturarVentaService::class)->facturar([
        ['id_producto' => 1001, 'nombre' => 'Camiseta talla M azul', 'precio' => 50000, 'cantidad' => 1, 'descuento' => 0],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'vendedor_id' => $vendedor->id,
    ]);

    $detalle = $factura->detalles()->first();

    expect((float) $factura->total)->toBe(50000.0);
    expect($detalle->tipo_servicio)->toBe('propio');
    expect((float) $detalle->porcentaje_empresa)->toBe(90.0);
    expect($detalle->mecanico_id)->not->toBeNull();
    expect((float) $detalle->getValorEmpresaAttribute())->toBe(45000.0);
});

test('sin vendedor asignado la venta directa no genera comision', function () {
    $empresa = crearEmpresaComisionTest(['tipo_negocio' => 'tienda', 'usa_comision_vendedores' => true]);
    crearProductoComisionTest($empresa);

    $factura = app(FacturarVentaService::class)->facturar([
        ['id_producto' => 1001, 'nombre' => 'Camiseta talla M azul', 'precio' => 50000, 'cantidad' => 1, 'descuento' => 0],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        // sin 'vendedor_id': venta directa del cajero/admin, sin comision.
    ]);

    $detalle = $factura->detalles()->first();

    expect($detalle->tipo_servicio)->toBeNull();
    expect($detalle->mecanico_id)->toBeNull();
    expect($detalle->porcentaje_empresa)->toBeNull();
});

test('la comision no aplica si el toggle usa_comision_vendedores esta apagado', function () {
    $empresa = crearEmpresaComisionTest(['tipo_negocio' => 'tienda', 'usa_comision_vendedores' => false]);
    crearProductoComisionTest($empresa);

    $vendedor = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Mecanico::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Vendedor de prueba',
        'rol' => Mecanico::ROL_VENDEDOR,
        'user_id' => $vendedor->id,
        'porcentaje_comision' => 10,
        'activo' => true,
    ]);

    $factura = app(FacturarVentaService::class)->facturar([
        ['id_producto' => 1001, 'nombre' => 'Camiseta talla M azul', 'precio' => 50000, 'cantidad' => 1, 'descuento' => 0],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'vendedor_id' => $vendedor->id,
    ]);

    $detalle = $factura->detalles()->first();

    expect($detalle->tipo_servicio)->toBeNull();
    expect($detalle->mecanico_id)->toBeNull();
});

test('un taller tambien puede comisionar a un vendedor (toggle universal)', function () {
    $empresa = crearEmpresaComisionTest(['tipo_negocio' => 'taller', 'usa_comision_vendedores' => true]);
    crearProductoComisionTest($empresa);

    $vendedor = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Mecanico::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Vendedor de repuestos',
        'rol' => Mecanico::ROL_VENDEDOR,
        'user_id' => $vendedor->id,
        'porcentaje_comision' => 15,
        'activo' => true,
    ]);

    $factura = app(FacturarVentaService::class)->facturar([
        ['id_producto' => 1001, 'nombre' => 'Camiseta talla M azul', 'precio' => 50000, 'cantidad' => 1, 'descuento' => 0],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'vendedor_id' => $vendedor->id,
    ]);

    $detalle = $factura->detalles()->first();

    expect($detalle->tipo_servicio)->toBe('propio');
    expect((float) $detalle->porcentaje_empresa)->toBe(85.0);
    expect($detalle->mecanico_id)->not->toBeNull();
});

test('bar y restaurante no genera comision de vendedor sobre productos, solo propina', function () {
    $empresa = crearEmpresaComisionTest(['tipo_negocio' => 'bar_restaurante', 'porcentaje_propina' => 10]);
    crearProductoComisionTest($empresa, 21160);

    $mesero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Mecanico::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Mesero de prueba',
        'rol' => Mecanico::ROL_MESERO,
        'user_id' => $mesero->id,
        'porcentaje_comision' => 5,
        'activo' => true,
    ]);

    $factura = app(FacturarVentaService::class)->facturar([
        ['id_producto' => 1001, 'nombre' => 'Camiseta talla M azul', 'precio' => 21160, 'cantidad' => 1, 'descuento' => 0],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'vendedor_id' => $mesero->id,
        'propina_monto' => 423.2, // 2% -- debe redondearse a entero y NO sumarse al total
    ]);

    $factura->refresh();
    $detalle = $factura->detalles()->first();

    // La propina es solo una sugerencia: no genera detalle ni afecta el total.
    expect($detalle->tipo_servicio)->toBeNull();
    expect($detalle->mecanico_id)->toBeNull();
    expect($factura->detalles()->count())->toBe(1);
    expect((float) $factura->total)->toBe(21160.0);
    expect((float) $factura->propina_sugerida)->toBe(423.0);
});
