<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\Product;
use App\Models\User;
use App\Services\Ubl21\Ubl21CreditNoteService;
use Illuminate\Support\Facades\Http;

// Nota credito con el proveedor alterno UBL 2.1 -- calca el flujo de
// FactusCreditNoteService (billing_reference apuntando a la factura
// electronica original: numero/cufe/fecha).

function crearDevolucionUbl21Test(): Devolucion
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa nota credito test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'factura_electronica_proveedor' => 'ubl21',
        'ubl21_base_url' => 'https://fake-ubl21.test/api/ubl2.1',
        'ubl21_api_token' => 'fake-token',
        'ubl21_prefix' => 'SETP',
        'ubl21_resolution_number' => '18760000001',
    ]);

    $producto = Product::create([
        'id_producto' => 7001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto devuelto',
        'precio_venta1' => 50000,
        'existencias' => 10,
    ]);

    $cliente = Actor::where('empresa_id', $empresa->id)->where('nombre', 'CONSUMIDOR FINAL')->first();

    $factura = Factura::create([
        'empresa_id' => $empresa->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
        'tipo_pago' => 'contado',
        'medio_pago' => 'efectivo',
        'fecha' => now(),
        'fecha_compra' => now(),
        'total' => 50000,
        'saldo' => 0,
        'estado_pago' => 'pagada',
        'ubl21_document_number' => 'SETP1',
        'ubl21_cufe' => 'cufe-original-123',
        'ubl21_status' => 'validada',
    ]);

    $detalle = FacturaDetalle::create([
        'factura_id' => $factura->id,
        'producto_id' => $producto->id_producto,
        'descripcion_larga' => $producto->descripcion_larga,
        'cantidad' => 1,
        'precio' => 50000,
        'subtotal' => 50000,
    ]);

    $devolucion = Devolucion::create([
        'empresa_id' => $empresa->id,
        'factura_id' => $factura->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'fecha' => now(),
        'total' => 50000,
        'observaciones' => 'Devolucion de prueba',
    ]);

    DevolucionDetalle::create([
        'devolucion_id' => $devolucion->id,
        'factura_detalle_id' => $detalle->id,
        'producto_id' => $producto->id_producto,
        'descripcion_larga' => $producto->descripcion_larga,
        'cantidad' => 1,
        'precio' => 50000,
        'subtotal' => 50000,
    ]);

    return $devolucion->fresh();
}

test('la nota credito referencia el numero y cufe de la factura electronica original', function () {
    $devolucion = crearDevolucionUbl21Test();

    $payload = app(\App\Services\Ubl21\Ubl21CreditNotePayloadBuilder::class)->build($devolucion);

    expect($payload['billing_reference']['number'])->toBe('SETP1');
    expect($payload['billing_reference']['uuid'])->toBe('cufe-original-123');
    expect($payload['type_document_id'])->toBe(4);
    expect($payload['prefix'])->toBe('SETP');
});

test('validate() guarda el numero/cufe de la nota credito devueltos por el proveedor', function () {
    $devolucion = crearDevolucionUbl21Test();

    Http::fake([
        'fake-ubl21.test/*' => Http::response([
            'success' => true,
            'is_valid' => true,
            'message' => 'Documento #SETPNC1 generado y validado.',
            'cufe' => 'cufe-nota-credito-456',
            'QRStr' => 'https://fake-ubl21.test/qr/nc1',
        ], 200),
    ]);

    app(Ubl21CreditNoteService::class)->validate($devolucion);

    $devolucion->refresh();

    expect($devolucion->ubl21_credit_note_status)->toBe('validada');
    expect($devolucion->ubl21_credit_note_number)->toBe('SETPNC1');
    expect($devolucion->ubl21_credit_note_cufe)->toBe('cufe-nota-credito-456');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/credit-note'));
});
