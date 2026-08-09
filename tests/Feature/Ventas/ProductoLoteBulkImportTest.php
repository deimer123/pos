<?php

use App\Imports\ProductoLoteBulkImport;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;
use Illuminate\Support\Collection;

// Carga masiva de productos con lotes (plantilla "Lote"/"Fecha de
// vencimiento" en vez de "Talla"/"Color"), calco de ProductoRopaBulkImport.
// Se llama collection() directo (bypass del parseo real de Excel, que ya
// resuelve WithHeadingRow) con las mismas claves "slugged" que produciria
// una hoja real -- mismo shape que consume CompraBulkImport::validar().

function crearEmpresaLoteBulkTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería bulk de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'usa_lotes' => true,
    ]);

    return $empresa;
}

test('importa un producto nuevo con dos lotes, cada uno con codigo propio y stock 0', function () {
    $empresa = crearEmpresaLoteBulkTest();

    $import = new ProductoLoteBulkImport($empresa->id);

    $import->collection(new Collection([
        [
            'nombre_del_producto' => 'Acetaminofén 500mg',
            'lote' => 'L240523',
            'fecha_de_vencimiento' => '2027-05-01',
            'proveedor' => 'Distribuidora ABC',
            'departamento' => 'Droguería',
            'subfamilia' => 'Analgésicos',
            'precio_costo' => 3000,
            'descuento_comercial' => 0,
            'iva_compra' => 19,
            'iva_venta' => 19,
            'utilidad' => 30,
            'precio_de_venta' => null,
        ],
        [
            'nombre_del_producto' => 'Acetaminofén 500mg',
            'lote' => 'L240890',
            'fecha_de_vencimiento' => '2027-11-15',
        ],
    ]));

    $resumen = $import->resumen();
    expect($resumen['errores'])->toBe([]);
    expect($resumen['creados'])->toBe(1);
    expect($resumen['lotes_creados'])->toBe(2);

    $producto = Product::where('empresa_id', $empresa->id)->where('descripcion_larga', 'Acetaminofén 500mg')->firstOrFail();
    $lotes = ProductoLote::where('product_id', $producto->id)->orderBy('lote')->get();

    expect($lotes)->toHaveCount(2);
    expect($lotes[0]->lote)->toBe('L240523');
    expect($lotes[0]->fecha_vencimiento->toDateString())->toBe('2027-05-01');
    expect((float) $lotes[0]->stock)->toBe(0.0);
    expect($lotes[0]->codigo)->not->toBeNull();
    expect($lotes[1]->codigo)->not->toBe($lotes[0]->codigo);
});

test('fila sin fecha de vencimiento queda en error y no crea nada', function () {
    $empresa = crearEmpresaLoteBulkTest();

    $import = new ProductoLoteBulkImport($empresa->id);

    $import->collection(new Collection([
        [
            'nombre_del_producto' => 'Ibuprofeno 400mg',
            'lote' => 'L1',
            'fecha_de_vencimiento' => '',
            'precio_costo' => 2000,
            'utilidad' => 30,
        ],
    ]));

    $resumen = $import->resumen();
    expect($resumen['errores'])->not->toBe([]);
    expect(Product::where('empresa_id', $empresa->id)->where('descripcion_larga', 'Ibuprofeno 400mg')->exists())->toBeFalse();
});
