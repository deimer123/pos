<?php

use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;

// Campos nuevos de Producto para Drogueria (lote/fecha_vencimiento/
// registro_invima): campo simple por producto, sin manejo de stock por
// lote (decision tomada con el usuario) -- esta prueba solo confirma que
// persisten y castean bien, ver ProductResource::empresaUsaLotes() para
// el gate de visibilidad en el formulario (ahora toggle usa_lotes, no
// amarrado a tipo_negocio=drogueria).

test('lote, fecha_vencimiento y registro_invima se guardan y castean en Product', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    $producto = Product::create([
        'id_producto' => '90001',
        'empresa_id' => $empresa->id,
        'id_familia1' => 0,
        'id_familia2' => 0,
        'descripcion_larga' => 'Acetaminofén 500mg caja x 20',
        'tipo_producto' => 'producto',
        'precio_venta1' => 5000,
        'precio_costo' => 3000,
        'existencias' => 10,
        'maneja_inventario' => true,
        'iva_venta' => 0,
        'iva_compra' => 0,
        'lote' => 'L240523',
        'fecha_vencimiento' => '2028-05-01',
        'registro_invima' => 'INVIMA 2023M-0001234',
    ]);

    $fresco = $producto->fresh();

    expect($fresco->lote)->toBe('L240523');
    expect($fresco->fecha_vencimiento)->toBeInstanceOf(Carbon::class);
    expect($fresco->fecha_vencimiento->toDateString())->toBe('2028-05-01');
    expect($fresco->registro_invima)->toBe('INVIMA 2023M-0001234');
});
