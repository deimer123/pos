<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;
use App\Services\Ventas\FacturarVentaService;
use Livewire\Livewire;

// Vender un producto de droguería identificando un lote puntual (escaneando
// su codigo propio, ver CarritoVenta::agregarProducto($id, $varianteId,
// $loteId) y pos-catalogo-offline.js::buscarCoincidenciaExacta) debe
// descontar SOLO el stock de ese lote, no el de otros lotes del mismo
// producto -- mismo criterio que ya aplica para variantes.

function crearEmpresaFacturarLoteTest(bool $permiteNegativo = false): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería facturar test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'permite_stock_negativo' => $permiteNegativo,
    ]);

    return $empresa;
}

function crearProductoConDosLotesTest(User $empresa): array
{
    $producto = Product::create([
        'id_producto' => 3001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Ibuprofeno 400mg',
        'precio_venta1' => 8000,
        'existencias' => 0,
    ]);

    $loteViejo = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3001VIEJO', 'lote' => 'VIEJO', 'fecha_vencimiento' => '2027-01-01', 'stock' => 5,
    ]);

    $loteNuevo = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3001NUEVO', 'lote' => 'NUEVO', 'fecha_vencimiento' => '2028-01-01', 'stock' => 5,
    ]);

    return [$producto, $loteViejo, $loteNuevo];
}

test('facturar con producto_lote_id descuenta solo el stock de ese lote', function () {
    $empresa = crearEmpresaFacturarLoteTest();
    [$producto, $loteViejo, $loteNuevo] = crearProductoConDosLotesTest($empresa);

    $factura = app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 3001,
            'nombre' => 'Ibuprofeno 400mg (Lote VIEJO)',
            'precio' => 8000,
            'cantidad' => 3,
            'descuento' => 0,
            'producto_lote_id' => $loteViejo->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]);

    expect((float) $loteViejo->fresh()->stock)->toBe(2.0);
    expect((float) $loteNuevo->fresh()->stock)->toBe(5.0);

    $detalle = $factura->detalles()->first();
    expect($detalle->producto_lote_id)->toBe($loteViejo->id);
    expect((float) $factura->total)->toBe(24000.0);
});

test('sin permitir stock negativo, vender mas de lo que tiene ESE lote falla aunque el producto tenga stock en otro lote', function () {
    $empresa = crearEmpresaFacturarLoteTest(permiteNegativo: false);
    [$producto, $loteViejo, $loteNuevo] = crearProductoConDosLotesTest($empresa);

    expect(fn () => app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 3001,
            'nombre' => 'Ibuprofeno 400mg (Lote VIEJO)',
            'precio' => 8000,
            'cantidad' => 100,
            'descuento' => 0,
            'producto_lote_id' => $loteViejo->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]))->toThrow(\Exception::class);

    expect((float) $loteViejo->fresh()->stock)->toBe(5.0);
    expect((float) $loteNuevo->fresh()->stock)->toBe(5.0);
});

test('devolverItemFactura repone el stock al lote correcto', function () {
    $empresa = crearEmpresaFacturarLoteTest();
    [$producto, $loteViejo, $loteNuevo] = crearProductoConDosLotesTest($empresa);

    $factura = app(FacturarVentaService::class)->facturar([
        [
            'id_producto' => 3001,
            'nombre' => 'Ibuprofeno 400mg (Lote VIEJO)',
            'precio' => 8000,
            'cantidad' => 3,
            'descuento' => 0,
            'producto_lote_id' => $loteViejo->id,
        ],
    ], [
        'empresa_id' => $empresa->id,
        'user_id' => $empresa->id,
    ]);

    expect((float) $loteViejo->fresh()->stock)->toBe(2.0);

    Livewire::actingAs($empresa)
        ->test(\App\Livewire\CarritoVenta::class)
        ->call('seleccionarFactura', $factura->id)
        ->call('devolverItemFactura', $factura->detalles()->first()->id, 3);

    expect((float) $loteViejo->fresh()->stock)->toBe(5.0);
    expect((float) $loteNuevo->fresh()->stock)->toBe(5.0);
});
