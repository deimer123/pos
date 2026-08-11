<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\Product;
use App\Models\User;
use App\Services\Ubl21\Ubl21InvoicePayloadBuilder;

// Cubre el armado del InvoiceRequest (schema del proveedor UBL 2.1) a
// partir de una Factura real -- en particular que los codigos DANE de
// municipio/departamento salen directo de ciudades/departamentos.codigo_dian
// (sin llamada a ninguna API externa, a diferencia de Factus).

function crearFacturaElectronicaUbl21Test(): Factura
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa payload test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'ubl21_prefix' => 'SETP',
        'ubl21_resolution_number' => '18760000001',
    ]);

    $producto = Product::create([
        'id_producto' => 6001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto con IVA',
        'precio_venta1' => 119000,
        'iva_venta' => 19,
        'existencias' => 10,
    ]);

    $cliente = \App\Models\Actor::where('empresa_id', $empresa->id)->where('nombre', 'CONSUMIDOR FINAL')->first();

    $factura = Factura::create([
        'empresa_id' => $empresa->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
        'tipo_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'fecha' => now(),
        'fecha_compra' => now(),
        'total' => 119000,
        'saldo' => 0,
        'estado_pago' => 'pagada',
    ]);

    FacturaDetalle::create([
        'factura_id' => $factura->id,
        'producto_id' => $producto->id_producto,
        'descripcion_larga' => $producto->descripcion_larga,
        'cantidad' => 1,
        'precio' => 119000,
        'subtotal' => 119000,
    ]);

    return $factura->fresh();
}

test('el payload usa los codigos DANE directos de ciudad/departamento para el cliente', function () {
    $factura = crearFacturaElectronicaUbl21Test();

    $payload = app(Ubl21InvoicePayloadBuilder::class)->build($factura);

    expect($payload['customer']['municipality_code'])->toBe('68001');
    expect($payload['customer']['department_code'])->toBe('68');
    expect($payload['customer']['type_document_identification_code'])->toBe('31'); // tipo_documento_id=6 (NIT) -> DIAN '31'
    expect($payload['customer']['country_code'])->toBe('CO');
});

test('el payload calcula base e IVA separados a partir del precio con impuesto incluido', function () {
    $factura = crearFacturaElectronicaUbl21Test();

    $payload = app(Ubl21InvoicePayloadBuilder::class)->build($factura);

    $linea = $payload['invoice_lines'][0];
    expect($linea['price_amount'])->toBe(100000.0);
    expect($linea['line_extension_amount'])->toBe(100000.0);
    expect($linea['tax_totals'][0]['tax_amount'])->toBe(19000.0);

    expect($payload['tax_totals'][0]['tax_amount'])->toBe(19000.0);
    expect($payload['legal_monetary_total']['payable_amount'])->toBe(119000.0);
    expect($payload['prefix'])->toBe('SETP');
    expect($payload['resolution_number'])->toBe('18760000001');
});

test('build lanza excepcion si la empresa no tiene prefijo ubl21 configurado', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Sin prefijo',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    $producto = Product::create([
        'id_producto' => 6002,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto',
        'precio_venta1' => 10000,
        'existencias' => 1,
    ]);

    $cliente = \App\Models\Actor::where('empresa_id', $empresa->id)->where('nombre', 'CONSUMIDOR FINAL')->first();

    $factura = Factura::create([
        'empresa_id' => $empresa->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
        'tipo_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'fecha' => now(),
        'fecha_compra' => now(),
        'total' => 10000,
        'saldo' => 0,
        'estado_pago' => 'pagada',
    ]);

    FacturaDetalle::create([
        'factura_id' => $factura->id,
        'producto_id' => $producto->id_producto,
        'descripcion_larga' => $producto->descripcion_larga,
        'cantidad' => 1,
        'precio' => 10000,
        'subtotal' => 10000,
    ]);

    expect(fn () => app(Ubl21InvoicePayloadBuilder::class)->build($factura->fresh()))
        ->toThrow(\InvalidArgumentException::class);
});
