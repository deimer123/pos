<?php

use App\Livewire\PantallaCocina;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\OrdenMesaItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

// Regresion de la vulnerabilidad IDOR corregida en PantallaCocina::marcarListo:
// actualizaba OrdenMesaItem por id crudo sin filtrar por empresa_id.

test('marcarListo no puede modificar un item de cocina de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $mesaB = Mesa::create([
        'empresa_id' => $empresaB->id,
        'codigo' => 'M1',
        'nombre' => 'Mesa 1',
    ]);

    $productoB = Product::create([
        'id_producto' => 1,
        'empresa_id' => $empresaB->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto de empresa B',
    ]);

    $ordenB = OrdenMesa::create([
        'empresa_id' => $empresaB->id,
        'mesa_id' => $mesaB->id,
        'estado' => 'abierta',
    ]);

    $itemB = OrdenMesaItem::create([
        'empresa_id' => $empresaB->id,
        'orden_mesa_id' => $ordenB->id,
        'product_id' => $productoB->id,
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'subtotal' => 1000,
        'estado_cocina' => 'enviado',
    ]);

    $this->actingAs($empresaA);

    Livewire::test(PantallaCocina::class)
        ->call('marcarListo', $itemB->id);

    expect($itemB->fresh()->estado_cocina)->toBe('enviado');
});
