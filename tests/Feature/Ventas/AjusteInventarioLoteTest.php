<?php

use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;
use App\Services\Inventario\AplicarAjusteInventarioService;

// Ajuste de Inventario con lotes: cada linea con producto_lote_id mueve
// exclusivamente el stock DE ESE LOTE (no el de otros lotes del mismo
// producto), calco de como ya funciona con producto_variante_id. Cubre
// AplicarAjusteInventarioService::aplicarConLote().

test('un ajuste tipo "ajuste" con producto_lote_id mueve solo el stock de ese lote', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Tienda de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'usa_lotes' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 3001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Leche en polvo',
        'precio_venta1' => 8000,
        'existencias' => 13,
    ]);

    $loteA = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3001A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 10,
    ]);
    $loteB = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3001B', 'lote' => 'LOTE-B', 'fecha_vencimiento' => '2027-06-01', 'stock' => 3,
    ]);

    $ajuste = AjusteInventario::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => $empresa->id,
        'tipo' => 'ajuste',
        'observacion' => 'Conteo fisico',
        'estado' => 'confirmado',
    ]);

    AjusteInventarioDetalle::create([
        'ajuste_inventario_id' => $ajuste->id,
        'producto_id' => $producto->id_producto,
        'producto_lote_id' => $loteA->id,
        'cantidad_nueva' => 25,
    ]);

    app(AplicarAjusteInventarioService::class)->aplicar($ajuste);

    expect((float) $loteA->fresh()->stock)->toBe(25.0);
    expect((float) $loteB->fresh()->stock)->toBe(3.0);
    expect((float) $producto->fresh()->existencias)->toBe(28.0);
});

test('un ajuste tipo "inventario_nuevo" pone en 0 todos los lotes de la empresa y luego fija el nuevo stock de la linea', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'usa_lotes' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 3002,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Ibuprofeno 400mg',
        'precio_venta1' => 3000,
        'existencias' => 40,
    ]);

    $loteA = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3002A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 25,
    ]);
    $loteB = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3002B', 'lote' => 'LOTE-B', 'fecha_vencimiento' => '2027-06-01', 'stock' => 15,
    ]);

    $ajuste = AjusteInventario::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => $empresa->id,
        'tipo' => 'inventario_nuevo',
        'observacion' => 'Inventario general',
        'estado' => 'confirmado',
    ]);

    // Solo LOTE-A trae linea en el nuevo inventario -- LOTE-B no aparece,
    // asi que debe quedar en 0 (fue puesto en 0 junto con todos los lotes
    // de la empresa al iniciar un inventario_nuevo).
    AjusteInventarioDetalle::create([
        'ajuste_inventario_id' => $ajuste->id,
        'producto_id' => $producto->id_producto,
        'producto_lote_id' => $loteA->id,
        'cantidad_nueva' => 8,
    ]);

    app(AplicarAjusteInventarioService::class)->aplicar($ajuste);

    expect((float) $loteA->fresh()->stock)->toBe(8.0);
    expect((float) $loteB->fresh()->stock)->toBe(0.0);
    expect((float) $producto->fresh()->existencias)->toBe(8.0);
});

test('un ajuste que dejaria el stock de un lote en negativo lanza excepcion', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Tienda de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'usa_lotes' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 3003,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Yogurt',
        'precio_venta1' => 2500,
        'existencias' => 5,
    ]);

    $lote = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '3003A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 5,
    ]);

    $ajuste = AjusteInventario::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => $empresa->id,
        'tipo' => 'ajuste',
        'observacion' => 'Conteo fisico',
        'estado' => 'confirmado',
    ]);

    AjusteInventarioDetalle::create([
        'ajuste_inventario_id' => $ajuste->id,
        'producto_id' => $producto->id_producto,
        'producto_lote_id' => $lote->id,
        'cantidad_nueva' => -3,
    ]);

    app(AplicarAjusteInventarioService::class)->aplicar($ajuste);
})->throws(\Exception::class);
