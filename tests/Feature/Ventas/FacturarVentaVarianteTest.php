<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoVariante;
use App\Models\User;
use App\Services\Ventas\FacturarVentaService;
use Livewire\Livewire;

// Ropa y Calzado (variantes talla/color): no habia ningun test cubriendo el
// camino de dinero real (facturar/devolver) identificando una variante
// puntual -- solo existia para lotes (ver FacturarVentaServiceLoteTest.php,
// que se calca aca 1 a 1 para variantes).

function crearEmpresaFacturarVarianteTest(bool $permiteNegativo = false): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Almacén de ropa test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'ropa_calzado',
        'usa_variantes' => true,
        'permite_stock_negativo' => $permiteNegativo,
    ]);

    return $empresa;
}

function crearProductoConDosVariantesTest(User $empresa): array
{
    $producto = Product::create([
        'id_producto' => 4001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Camisa Polo',
        'precio_venta1' => 60000,
        'existencias' => 0,
    ]);

    $varianteM = ProductoVariante::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '4001M', 'nombre' => 'Talla M - Azul',
        'atributos' => ['talla' => 'M', 'color' => 'Azul'], 'precio_extra' => 0, 'stock' => 5, 'activo' => true,
    ]);

    $varianteL = ProductoVariante::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '4001L', 'nombre' => 'Talla L - Azul',
        'atributos' => ['talla' => 'L', 'color' => 'Azul'], 'precio_extra' => 0, 'stock' => 5, 'activo' => true,
    ]);

    return [$producto, $varianteM, $varianteL];
}

test('facturar con producto_variante_id descuenta solo el stock de esa variante', function () {
    $empresa = crearEmpresaFacturarVarianteTest();
    [$producto, $varianteM, $varianteL] = crearProductoConDosVariantesTest($empresa);

    $factura = app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 4001,
            'nombre' => 'Camisa Polo (Talla M - Azul)',
            'precio' => 60000,
            'cantidad' => 2,
            'descuento' => 0,
            'producto_variante_id' => $varianteM->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]);

    expect((float) $varianteM->fresh()->stock)->toBe(3.0);
    expect((float) $varianteL->fresh()->stock)->toBe(5.0);

    $detalle = $factura->detalles()->first();
    expect($detalle->producto_variante_id)->toBe($varianteM->id);
    expect((float) $factura->total)->toBe(120000.0);
});

test('sin permitir stock negativo, vender mas de lo que tiene ESA variante falla aunque el producto tenga stock en otra variante', function () {
    $empresa = crearEmpresaFacturarVarianteTest(permiteNegativo: false);
    [$producto, $varianteM, $varianteL] = crearProductoConDosVariantesTest($empresa);

    expect(fn () => app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 4001,
            'nombre' => 'Camisa Polo (Talla M - Azul)',
            'precio' => 60000,
            'cantidad' => 100,
            'descuento' => 0,
            'producto_variante_id' => $varianteM->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]))->toThrow(\Exception::class);

    expect((float) $varianteM->fresh()->stock)->toBe(5.0);
    expect((float) $varianteL->fresh()->stock)->toBe(5.0);
});

test('devolverItemFactura repone el stock a la variante correcta', function () {
    $empresa = crearEmpresaFacturarVarianteTest();
    [$producto, $varianteM, $varianteL] = crearProductoConDosVariantesTest($empresa);

    $factura = app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 4001,
            'nombre' => 'Camisa Polo (Talla M - Azul)',
            'precio' => 60000,
            'cantidad' => 2,
            'descuento' => 0,
            'producto_variante_id' => $varianteM->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]);

    expect((float) $varianteM->fresh()->stock)->toBe(3.0);

    Livewire::actingAs($empresa)
        ->test(\App\Livewire\CarritoVenta::class)
        ->call('seleccionarFactura', $factura->id)
        ->call('devolverItemFactura', $factura->detalles()->first()->id, 2);

    expect((float) $varianteM->fresh()->stock)->toBe(5.0);
    expect((float) $varianteL->fresh()->stock)->toBe(5.0);
});
