<?php

use App\Imports\CompraBulkImport;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;
use Illuminate\Support\Collection;

// Carga masiva de una factura de compra con columnas "Lote"/"Fecha de
// vencimiento" (conLotes=true): cada linea debe resolver/crear el lote
// (CompraResource::resolverLoteId(), mismo mecanismo que la carga manual) y
// sumarle el stock comprado, dejando producto_lote_id en el CompraDetalle.

function crearFixtureCompraBulkLoteTest(): array
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería bulk de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'usa_lotes' => true,
    ]);

    $siguienteClip = (int) (Actor::where('empresa_id', $empresa->id)->max('id_clip_pro') ?? 0) + 1;
    $proveedor = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => $siguienteClip,
        'tipo' => 2,
        'tipo_persona' => 'natural',
        'tipo_documento_id' => 6,
        'nombre' => 'Proveedor bulk de prueba',
    ]);

    return compact('empresa', 'proveedor');
}

test('importar compra con Lote/Fecha de vencimiento crea el lote y le suma el stock comprado', function () {
    ['empresa' => $empresa, 'proveedor' => $proveedor] = crearFixtureCompraBulkLoteTest();

    $import = new CompraBulkImport(
        empresaId: $empresa->id,
        userId: $empresa->id,
        proveedorActorId: $proveedor->id,
        proveedorClip: $proveedor->id_clip_pro,
        numeroFactura: 'BULK-1',
        tipoPago: 'contado',
        fecha: now()->toDateString(),
        fechaVencimiento: now()->toDateString(),
        conLotes: true,
    );

    $import->collection(new Collection([
        [
            'producto' => 'Acetaminofén 500mg',
            'lote' => 'L240523',
            'fecha_de_vencimiento' => '2027-05-01',
            'cantidad' => 20,
            'costo_unitario' => 3000,
            'descuento_comercial' => 0,
            'iva' => 19,
            'utilidad' => 30,
        ],
    ]));

    $resumen = $import->resumen();
    expect($resumen['errores'])->toBe([]);
    expect($resumen['compra'])->not->toBeNull();

    $producto = Product::where('empresa_id', $empresa->id)->where('descripcion_larga', 'Acetaminofén 500mg')->firstOrFail();
    $lote = ProductoLote::where('product_id', $producto->id)->where('lote', 'L240523')->firstOrFail();

    expect((float) $lote->stock)->toBe(20.0);
    expect($lote->fecha_vencimiento->toDateString())->toBe('2027-05-01');

    $detalle = CompraDetalle::where('compra_id', $resumen['compra']->id)->firstOrFail();
    expect($detalle->producto_lote_id)->toBe($lote->id);
});

test('importar una segunda compra con el mismo numero de lote reutiliza el lote y le suma stock', function () {
    ['empresa' => $empresa, 'proveedor' => $proveedor] = crearFixtureCompraBulkLoteTest();

    $producto = Product::create([
        'id_producto' => 5001,
        'empresa_id' => $empresa->id,
        'id_proveedor' => $proveedor->id_clip_pro,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Ibuprofeno 400mg',
        'precio_venta1' => 3000,
        'existencias' => 0,
    ]);

    $loteExistente = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '5001A', 'lote' => 'LOTE-X', 'fecha_vencimiento' => '2027-01-01', 'stock' => 5,
    ]);

    $import = new CompraBulkImport(
        empresaId: $empresa->id,
        userId: $empresa->id,
        proveedorActorId: $proveedor->id,
        proveedorClip: $proveedor->id_clip_pro,
        numeroFactura: 'BULK-2',
        tipoPago: 'contado',
        fecha: now()->toDateString(),
        fechaVencimiento: now()->toDateString(),
        conLotes: true,
    );

    $import->collection(new Collection([
        [
            'producto' => 'Ibuprofeno 400mg',
            'lote' => 'LOTE-X',
            'fecha_de_vencimiento' => '2027-01-01',
            'cantidad' => 10,
            'costo_unitario' => 1500,
            'iva' => 19,
            'utilidad' => 30,
        ],
    ]));

    expect($import->resumen()['errores'])->toBe([]);
    expect(ProductoLote::where('product_id', $producto->id)->count())->toBe(1);
    expect((float) $loteExistente->fresh()->stock)->toBe(15.0);
});

test('fila con Lote pero sin Fecha de vencimiento queda en error y no crea la compra', function () {
    ['empresa' => $empresa, 'proveedor' => $proveedor] = crearFixtureCompraBulkLoteTest();

    $import = new CompraBulkImport(
        empresaId: $empresa->id,
        userId: $empresa->id,
        proveedorActorId: $proveedor->id,
        proveedorClip: $proveedor->id_clip_pro,
        numeroFactura: 'BULK-3',
        tipoPago: 'contado',
        fecha: now()->toDateString(),
        fechaVencimiento: now()->toDateString(),
        conLotes: true,
    );

    $import->collection(new Collection([
        [
            'producto' => 'Producto sin fecha',
            'lote' => 'L1',
            'fecha_de_vencimiento' => '',
            'cantidad' => 5,
            'costo_unitario' => 1000,
            'iva' => 19,
            'utilidad' => 30,
        ],
    ]));

    $resumen = $import->resumen();
    expect($resumen['errores'])->not->toBe([]);
    expect($resumen['compra'])->toBeNull();
    expect(Compra::where('empresa_id', $empresa->id)->where('numero_factura', 'BULK-3')->exists())->toBeFalse();
});
