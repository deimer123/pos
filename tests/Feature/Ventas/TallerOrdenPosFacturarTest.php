<?php

use App\Livewire\TallerOrdenPos;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\TallerOrden;
use App\Models\TallerRepuesto;
use App\Models\User;
use Livewire\Livewire;

// Regresion de los mismos 4 bugs ya encontrados y corregidos en
// ServicioTecnicoOrdenPos::facturar() (ver ServicioTecnicoOrdenPosFacturarTest.php):
// esta era la copia ORIGINAL de la que se calco ese modulo, y se quedo con
// el mismo bug sin corregir hasta ahora (3 columnas de `facturas` con
// valores fuera de su ENUM, y `factura_detalles.producto_id` NOT NULL
// recibiendo null para items manuales/de tercero sin Product real).

test('facturar una orden de taller con un item manual (sin producto) y medio de pago no estandar no revienta', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa taller facturar test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'taller',
        'usa_taller' => true,
    ]);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'cliente_nombre' => 'Cliente de prueba',
        'placa' => 'ABC123',
        'marca' => 'Yamaha',
        'modelo' => 'XTZ 150',
        'estado' => 'en_proceso',
    ]);

    TallerRepuesto::create([
        'orden_id' => $orden->id,
        'producto_id' => null,
        'descripcion' => 'Mano de obra: cambio de aceite',
        'cantidad' => 1,
        'precio_unitario' => 25000,
        'subtotal' => 25000,
        'tipo' => 'mano_obra',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresa);

    Livewire::test(TallerOrdenPos::class, ['ordenId' => $orden->id])
        ->set('tipoPago', 'contado')
        ->set('medioPago', 'nequi')
        ->call('facturar');

    $orden->refresh();

    expect($orden->estado)->toBe('entregado');
    expect($orden->factura_id)->not->toBeNull();

    $factura = Factura::find($orden->factura_id);

    expect($factura)->not->toBeNull();
    expect($factura->tipo_factura)->toBe('salida');
    expect($factura->estado_pago)->toBe('pagada');
    expect($factura->medio_pago)->toBe('otro');
    expect((float) $factura->total)->toBe(25000.0);

    $detalle = $factura->detalles->first();
    expect($detalle->producto_id)->toBe(0);
    expect($detalle->descripcion_larga)->toBe('Mano de obra: cambio de aceite');
});
