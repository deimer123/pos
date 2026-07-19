<?php

use App\Livewire\TallerOrdenPos;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use App\Models\User;
use Livewire\Livewire;

// Regresion de la vulnerabilidad IDOR corregida en TallerOrdenPos: editarItem/
// guardarItem/quitarItem operaban sobre TallerRepuesto por id crudo, sin
// verificar que el repuesto perteneciera a la orden/empresa del usuario.

function crearOrdenTaller(int $empresaId): TallerOrden
{
    return TallerOrden::create([
        'empresa_id' => $empresaId,
        'cliente_nombre' => 'Cliente de prueba',
        'placa' => 'ABC123',
        'estado' => 'en_proceso',
    ]);
}

test('editarItem no puede leer un repuesto de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenTaller($empresaA->id);
    $ordenB = crearOrdenTaller($empresaB->id);

    $repuestoB = TallerRepuesto::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'SECRETO_EMPRESA_B',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 999,
    ]);

    $this->actingAs($empresaA);

    Livewire::test(TallerOrdenPos::class, ['ordenId' => $ordenA->id])
        ->call('editarItem', $repuestoB->id)
        ->assertSet('editandoId', null)
        ->assertSet('editDesc', '');
});

test('guardarItem no puede sobrescribir un repuesto de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenTaller($empresaA->id);
    $ordenB = crearOrdenTaller($empresaB->id);

    $repuestoB = TallerRepuesto::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'ORIGINAL',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresaA);

    // Simula un request Livewire manipulado directamente (no via editarItem,
    // que ya valida): fija editandoId a un repuesto de otra empresa y llama
    // guardarItem para confirmar que la propia actualizacion tambien se
    // protege por su cuenta (defensa en profundidad).
    Livewire::test(TallerOrdenPos::class, ['ordenId' => $ordenA->id])
        ->set('editandoId', $repuestoB->id)
        ->set('editDesc', 'HACKEADO')
        ->set('editCant', 1)
        ->set('editPrecio', 1)
        ->call('guardarItem');

    expect($repuestoB->fresh()->descripcion)->toBe('ORIGINAL');
});

test('quitarItem no puede borrar un repuesto de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenTaller($empresaA->id);
    $ordenB = crearOrdenTaller($empresaB->id);

    $repuestoB = TallerRepuesto::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'NO BORRAR',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresaA);

    Livewire::test(TallerOrdenPos::class, ['ordenId' => $ordenA->id])
        ->call('quitarItem', $repuestoB->id);

    expect(TallerRepuesto::find($repuestoB->id))->not->toBeNull();
});
