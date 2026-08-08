<?php

use App\Livewire\ServicioTecnicoOrdenPos;
use App\Models\ServicioTecnicoItem;
use App\Models\ServicioTecnicoOrden;
use App\Models\User;
use Livewire\Livewire;

// Calco de TallerOrdenPosIdorTest -- ServicioTecnicoOrdenPos se copio de
// TallerOrdenPos con el mismo guard por empresa/orden en editarItem/
// guardarItem/quitarItem, esta prueba confirma que la copia lo conservo.

function crearOrdenServicioTecnico(int $empresaId): ServicioTecnicoOrden
{
    return ServicioTecnicoOrden::create([
        'empresa_id' => $empresaId,
        'cliente_nombre' => 'Cliente de prueba',
        'imei_serial' => '123456789012345',
        'estado' => 'en_proceso',
    ]);
}

test('editarItem no puede leer un item de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenServicioTecnico($empresaA->id);
    $ordenB = crearOrdenServicioTecnico($empresaB->id);

    $itemB = ServicioTecnicoItem::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'SECRETO_EMPRESA_B',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 999,
    ]);

    $this->actingAs($empresaA);

    Livewire::test(ServicioTecnicoOrdenPos::class, ['ordenId' => $ordenA->id])
        ->call('editarItem', $itemB->id)
        ->assertSet('editandoId', null)
        ->assertSet('editDesc', '');
});

test('guardarItem no puede sobrescribir un item de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenServicioTecnico($empresaA->id);
    $ordenB = crearOrdenServicioTecnico($empresaB->id);

    $itemB = ServicioTecnicoItem::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'ORIGINAL',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresaA);

    Livewire::test(ServicioTecnicoOrdenPos::class, ['ordenId' => $ordenA->id])
        ->set('editandoId', $itemB->id)
        ->set('editDesc', 'HACKEADO')
        ->set('editCant', 1)
        ->set('editPrecio', 1)
        ->call('guardarItem');

    expect($itemB->fresh()->descripcion)->toBe('ORIGINAL');
});

test('quitarItem no puede borrar un item de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $ordenA = crearOrdenServicioTecnico($empresaA->id);
    $ordenB = crearOrdenServicioTecnico($empresaB->id);

    $itemB = ServicioTecnicoItem::create([
        'orden_id' => $ordenB->id,
        'descripcion' => 'NO BORRAR',
        'cantidad' => 1,
        'precio_unitario' => 500,
        'subtotal' => 500,
        'tipo' => 'repuesto',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresaA);

    Livewire::test(ServicioTecnicoOrdenPos::class, ['ordenId' => $ordenA->id])
        ->call('quitarItem', $itemB->id);

    expect(ServicioTecnicoItem::find($itemB->id))->not->toBeNull();
});
