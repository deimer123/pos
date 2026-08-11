<?php

use App\Mail\Ubl21FacturaElectronicaMail;
use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Product;
use App\Models\User;
use App\Services\Ventas\FacturarVentaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

// Segundo proveedor de facturacion electronica (UBL 2.1): estos tests
// cubren el dispatcher agregado en FacturarVentaService (elegir Factus vs
// el proveedor alterno segun configuracion_empresas.factura_electronica_proveedor)
// sin tocar el camino existente de Factus. Http::fake() en vez de golpear
// la API real -- ver App\Services\Ubl21\Ubl21Client.

function crearEmpresaUbl21Test(array $overrides = []): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create(array_merge([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa UBL21 test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'factura_electronica_proveedor' => 'ubl21',
        'ubl21_base_url' => 'https://fake-ubl21.test/api/ubl2.1',
        'ubl21_api_token' => 'fake-token',
        'ubl21_environment' => 'habilitacion',
        'ubl21_nit' => '900123456',
        'ubl21_dv' => '1',
        'ubl21_prefix' => 'SETP',
        'ubl21_resolution_number' => '18760000001',
        'ubl21_numbering_from' => 1,
        'ubl21_numbering_to' => 5000,
    ], $overrides));

    return $empresa;
}

function crearProductoUbl21Test(User $empresa): Product
{
    return Product::create([
        'id_producto' => 5001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto electronico test',
        'precio_venta1' => 119000,
        'iva_venta' => 19,
        'existencias' => 100,
    ]);
}

test('facturacionElectronicaDisponible es true con proveedor ubl21 completo y false si falta algo', function () {
    $empresa = crearEmpresaUbl21Test();

    expect(app(FacturarVentaService::class)->facturacionElectronicaDisponible($empresa->id))->toBeTrue();

    $empresa->configuracion->update(['ubl21_prefix' => null]);

    expect(app(FacturarVentaService::class)->facturacionElectronicaDisponible($empresa->id))->toBeFalse();
});

test('facturar con proveedor ubl21 valida la factura contra el proveedor alterno', function () {
    $empresa = crearEmpresaUbl21Test();
    crearProductoUbl21Test($empresa);

    Http::fake([
        'fake-ubl21.test/*' => Http::response([
            'success' => true,
            'is_valid' => true,
            'message' => 'Documento #SETP1 generado y validado.',
            'cufe' => 'cufe-fake-123',
            'QRStr' => 'https://fake-ubl21.test/qr/1',
            'urlinvoicepdf' => 'https://fake-ubl21.test/pdf/1',
            'urlinvoicexml' => 'https://fake-ubl21.test/xml/1',
        ], 200),
    ]);

    $factura = app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 5001,
            'nombre' => 'Producto electronico test',
            'precio' => 119000,
            'cantidad' => 1,
            'descuento' => 0,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
    ]);

    expect($factura->ubl21_status)->toBe('validada');
    expect($factura->ubl21_document_number)->toBe('SETP1');
    expect($factura->ubl21_cufe)->toBe('cufe-fake-123');
    expect($factura->factus_status)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/invoice') && $request->hasHeader('Authorization', 'Bearer fake-token'));
});

test('si el proveedor ubl21 rechaza la factura, no se guarda la venta', function () {
    $empresa = crearEmpresaUbl21Test();
    crearProductoUbl21Test($empresa);

    Http::fake([
        'fake-ubl21.test/*' => Http::response([
            'success' => false,
            'is_valid' => false,
            'message' => 'Rechazado por la DIAN',
        ], 200),
    ]);

    expect(fn () => app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 5001,
            'nombre' => 'Producto electronico test',
            'precio' => 119000,
            'cantidad' => 1,
            'descuento' => 0,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
    ]))->toThrow(\RuntimeException::class);

    expect(Factura::count())->toBe(0);
});

test('empresas sin factura_electronica_proveedor configurado siguen usando Factus (compatibilidad)', function () {
    $empresa = crearEmpresaUbl21Test(['factura_electronica_proveedor' => null]);

    expect(FacturarVentaService::proveedorFacturaElectronica($empresa->configuracion->fresh()))->toBe('factus');
});

// El proveedor alterno no envia el correo al cliente por su cuenta (a
// diferencia de Factus) -- Ubl21InvoiceService::validate() lo suple. No
// debe mandarse a CONSUMIDOR FINAL ni a clientes sin correo real.
test('al validar con el proveedor alterno se envia el correo al cliente si tiene email real', function () {
    Mail::fake();

    $empresa = crearEmpresaUbl21Test();
    crearProductoUbl21Test($empresa);

    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 200,
        'tipo' => 1,
        'tipo_documento_id' => 6,
        'identificacion' => '555666777',
        'nombre' => 'Cliente con correo',
        'email' => 'cliente-real@example.com',
    ]);

    Http::fake([
        'fake-ubl21.test/*' => Http::response([
            'success' => true,
            'is_valid' => true,
            'message' => 'Documento #SETP2 generado y validado.',
            'cufe' => 'cufe-fake-456',
            'urlinvoicepdf' => 'https://fake-ubl21.test/pdf/2',
        ], 200),
    ]);

    app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 5001,
            'nombre' => 'Producto electronico test',
            'precio' => 119000,
            'cantidad' => 1,
            'descuento' => 0,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
        'cliente_id' => $cliente->id,
    ]);

    Mail::assertSent(Ubl21FacturaElectronicaMail::class, fn ($mail) => $mail->hasTo('cliente-real@example.com'));
});

test('al validar con el proveedor alterno NO se envia correo a CONSUMIDOR FINAL', function () {
    Mail::fake();

    $empresa = crearEmpresaUbl21Test();
    crearProductoUbl21Test($empresa);

    Http::fake([
        'fake-ubl21.test/*' => Http::response([
            'success' => true,
            'is_valid' => true,
            'message' => 'Documento #SETP3 generado y validado.',
            'cufe' => 'cufe-fake-789',
        ], 200),
    ]);

    app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 5001,
            'nombre' => 'Producto electronico test',
            'precio' => 119000,
            'cantidad' => 1,
            'descuento' => 0,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
    ]);

    Mail::assertNotSent(Ubl21FacturaElectronicaMail::class);
});
