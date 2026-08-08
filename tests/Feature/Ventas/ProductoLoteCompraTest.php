<?php

use App\Filament\Resources\CompraResource;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;

// Droguería: un producto puede tener varios lotes en stock a la vez, cada
// uno con su propia fecha de vencimiento y codigo de barras (calco de
// ProductoVariante) -- se dan de alta desde Compras, nunca a mano. Cubre
// CompraResource::resolverLoteId() (find-or-create por numero de lote) y
// Compra::confirmar()/anular() (mover el stock del lote puntual, no el de
// otros lotes del mismo producto).

function crearEmpresaLoteTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
    ]);

    return $empresa;
}

function crearProductoLoteTest(User $empresa): Product
{
    return Product::create([
        'id_producto' => 2001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Acetaminofén 500mg',
        'precio_venta1' => 5000,
        'existencias' => 0,
    ]);
}

function crearProveedorLoteTest(User $empresa): Actor
{
    $siguienteClip = (int) (Actor::where('empresa_id', $empresa->id)->max('id_clip_pro') ?? 0) + 1;

    return Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => $siguienteClip,
        'tipo' => 2, // 1=cliente,2=proveedor,3=ambos (ver create_actors_table)
        'tipo_persona' => 'natural',
        'tipo_documento_id' => 6, // NIT, sembrado en tests/Pest.php
        'nombre' => 'Proveedor de prueba',
    ]);
}

test('resolverLoteId crea un lote nuevo con stock 0 y codigo auto-sugerido', function () {
    $empresa = crearEmpresaLoteTest();
    $producto = crearProductoLoteTest($empresa);

    $loteId = CompraResource::resolverLoteId($producto->id, $empresa->id, 'L240523', '2028-05-01');

    $lote = ProductoLote::find($loteId);

    expect($lote)->not->toBeNull();
    expect($lote->lote)->toBe('L240523');
    expect((float) $lote->stock)->toBe(0.0);
    expect($lote->codigo)->not->toBeNull();
    expect($lote->fecha_vencimiento->toDateString())->toBe('2028-05-01');
});

test('resolverLoteId con el mismo numero de lote reutiliza la fila, no duplica', function () {
    $empresa = crearEmpresaLoteTest();
    $producto = crearProductoLoteTest($empresa);

    $idUno = CompraResource::resolverLoteId($producto->id, $empresa->id, 'L240523', '2028-05-01');
    $idDos = CompraResource::resolverLoteId($producto->id, $empresa->id, 'L240523', '2028-05-01');

    expect($idUno)->toBe($idDos);
    expect(ProductoLote::where('product_id', $producto->id)->count())->toBe(1);
});

test('resolverLoteId sin lote o sin fecha no crea nada', function () {
    $empresa = crearEmpresaLoteTest();
    $producto = crearProductoLoteTest($empresa);

    expect(CompraResource::resolverLoteId($producto->id, $empresa->id, '', '2028-05-01'))->toBeNull();
    expect(CompraResource::resolverLoteId($producto->id, $empresa->id, 'L1', null))->toBeNull();
    expect(ProductoLote::count())->toBe(0);
});

test('confirmar una compra con lote incrementa solo el stock de ese lote', function () {
    $empresa = crearEmpresaLoteTest();
    $producto = crearProductoLoteTest($empresa);

    $loteA = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '2001A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 10,
    ]);
    $loteB = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '2001B', 'lote' => 'LOTE-B', 'fecha_vencimiento' => '2027-06-01', 'stock' => 3,
    ]);

    $compra = Compra::create([
        'empresa_id' => $empresa->id, 'proveedor_id' => crearProveedorLoteTest($empresa)->id, 'user_id' => $empresa->id,
        'estado' => 'borrador', 'tipo_pago' => 'contado', 'fecha' => now(), 'numero_factura' => 'F-1',
    ]);

    CompraDetalle::create([
        'compra_id' => $compra->id,
        'product_id' => (string) $producto->id_producto,
        'producto_lote_id' => $loteA->id,
        'nombre_producto' => $producto->descripcion_larga,
        'cantidad' => 20,
        'costo_unitario' => 3000,
        'desc_comercial' => 0,
        'iva_pct' => 0,
        'utilidad_pct' => 0,
        'precio_venta' => 5000,
    ]);

    $compra->confirmar();

    expect((float) $loteA->fresh()->stock)->toBe(30.0);
    expect((float) $loteB->fresh()->stock)->toBe(3.0);
    expect((float) $producto->fresh()->existencias)->toBe(20.0);
});

test('anular una compra confirmada revierte solo el stock del lote de esa linea', function () {
    $empresa = crearEmpresaLoteTest();
    $producto = crearProductoLoteTest($empresa);

    $lote = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '2001A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 0,
    ]);

    $compra = Compra::create([
        'empresa_id' => $empresa->id, 'proveedor_id' => crearProveedorLoteTest($empresa)->id, 'user_id' => $empresa->id,
        'estado' => 'borrador', 'tipo_pago' => 'contado', 'fecha' => now(), 'numero_factura' => 'F-2',
    ]);

    CompraDetalle::create([
        'compra_id' => $compra->id,
        'product_id' => (string) $producto->id_producto,
        'producto_lote_id' => $lote->id,
        'nombre_producto' => $producto->descripcion_larga,
        'cantidad' => 15,
        'costo_unitario' => 3000,
        'desc_comercial' => 0,
        'iva_pct' => 0,
        'utilidad_pct' => 0,
        'precio_venta' => 5000,
    ]);

    $compra->confirmar();
    expect((float) $lote->fresh()->stock)->toBe(15.0);

    $compra->fresh()->anular();

    expect((float) $lote->fresh()->stock)->toBe(0.0);
    expect((float) $producto->fresh()->existencias)->toBe(0.0);
});
