<?php

use App\Filament\Resources\CompraResource\Pages\ViewCompra;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\ConfiguracionEmpresa;
use App\Models\DevolucionCompraDetalle;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Devolucion a proveedor con lotes: cuando la linea de la compra original
// trae producto_lote_id, la devolucion debe descontar el stock de ESE lote
// puntual (no solo el del producto), calco de como ya funciona con
// producto_variante_id en ViewCompra::registrarDevolucion.

function crearFixtureDevolucionLoteTest(): array
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'usa_lotes' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 4001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Acetaminofén 500mg',
        'precio_venta1' => 5000,
        'existencias' => 0,
    ]);

    $lote = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '4001A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 0,
    ]);

    $siguienteClip = (int) (Actor::where('empresa_id', $empresa->id)->max('id_clip_pro') ?? 0) + 1;
    $proveedor = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => $siguienteClip,
        'tipo' => 2, // proveedor
        'tipo_persona' => 'natural',
        'tipo_documento_id' => 6, // NIT, sembrado en tests/Pest.php
        'nombre' => 'Proveedor de prueba',
    ]);

    $compra = Compra::create([
        'empresa_id' => $empresa->id, 'proveedor_id' => $proveedor->id, 'user_id' => $empresa->id,
        'estado' => 'borrador', 'tipo_pago' => 'contado', 'fecha' => now(), 'numero_factura' => 'F-10',
    ]);

    CompraDetalle::create([
        'compra_id' => $compra->id,
        'product_id' => (string) $producto->id_producto,
        'producto_lote_id' => $lote->id,
        'nombre_producto' => $producto->descripcion_larga,
        'cantidad' => 20,
        'costo_unitario' => 3000,
        'desc_comercial' => 0,
        'iva_pct' => 0,
        'utilidad_pct' => 0,
        'precio_venta' => 5000,
    ]);

    // Deja la compra CONFIRMADA (mismo estado que produccion antes de una
    // devolucion), con el stock del lote ya incrementado por la compra.
    $compra->confirmar();

    return compact('empresa', 'producto', 'lote', 'compra');
}

test('registrar una devolucion total decrementa el stock del lote puntual, no solo el del producto', function () {
    ['empresa' => $empresa, 'producto' => $producto, 'lote' => $lote, 'compra' => $compra] = crearFixtureDevolucionLoteTest();

    expect((float) $lote->fresh()->stock)->toBe(20.0);
    expect((float) $producto->fresh()->existencias)->toBe(20.0);

    Livewire::actingAs($empresa)
        ->test(ViewCompra::class, ['record' => $compra->getKey()])
        ->mountAction('registrarDevolucion')
        ->setActionData(['tipo' => 'total', 'motivo' => 'Producto vencido'])
        ->callMountedAction();

    expect((float) $lote->fresh()->stock)->toBe(0.0);
    expect((float) $producto->fresh()->existencias)->toBe(0.0);

    $compraDetalle = CompraDetalle::where('compra_id', $compra->id)->firstOrFail();
    $detalleDevolucion = DevolucionCompraDetalle::where('compra_detalle_id', $compraDetalle->id)->first();

    expect($detalleDevolucion)->not->toBeNull();
    expect($detalleDevolucion->producto_lote_id)->toBe($lote->id);
    expect((float) $detalleDevolucion->cantidad)->toBe(20.0);
});

test('registrar una devolucion parcial decrementa el stock del lote solo en la cantidad devuelta', function () {
    ['empresa' => $empresa, 'producto' => $producto, 'lote' => $lote, 'compra' => $compra] = crearFixtureDevolucionLoteTest();

    $compraDetalle = CompraDetalle::where('compra_id', $compra->id)->firstOrFail();

    Livewire::actingAs($empresa)
        ->test(ViewCompra::class, ['record' => $compra->getKey()])
        ->mountAction('registrarDevolucion')
        ->setActionData([
            'tipo' => 'parcial',
            'motivo' => 'Unidades danadas',
            'detalles' => [
                [
                    'compra_detalle_id' => $compraDetalle->id,
                    'product_id' => $compraDetalle->product_id,
                    'producto_variante_id' => null,
                    'producto_lote_id' => $lote->id,
                    'codigo_ingresado' => $compraDetalle->codigo_ingresado,
                    'nombre_producto' => $producto->descripcion_larga,
                    'cantidad_original' => 20,
                    'cantidad' => 5,
                    'costo_unitario' => $compraDetalle->costo_unitario,
                    'desc_comercial' => $compraDetalle->desc_comercial,
                    'iva_pct' => $compraDetalle->iva_pct,
                ],
            ],
        ])
        ->callMountedAction();

    expect((float) $lote->fresh()->stock)->toBe(15.0);
    expect((float) $producto->fresh()->existencias)->toBe(15.0);

    $detalleDevolucion = DevolucionCompraDetalle::where('compra_detalle_id', $compraDetalle->id)->first();
    expect($detalleDevolucion)->not->toBeNull();
    expect($detalleDevolucion->producto_lote_id)->toBe($lote->id);
    expect((float) $detalleDevolucion->cantidad)->toBe(5.0);
});
