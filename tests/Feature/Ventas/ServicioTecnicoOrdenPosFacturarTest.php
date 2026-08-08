<?php

use App\Livewire\ServicioTecnicoOrdenPos;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\ServicioTecnicoItem;
use App\Models\ServicioTecnicoOrden;
use App\Models\User;
use Livewire\Livewire;

// Regresion de 4 bugs encontrados probando facturar() de punta a punta en el
// navegador (contra MySQL en modo estricto): 3 columnas de `facturas` con
// valores que no existen en su ENUM (tipo_factura='venta', estado_pago=
// 'pagado', medio_pago='tarjeta'/'nequi'), y `factura_detalles.producto_id`
// (NOT NULL) recibiendo null para items manuales sin Product real. Mismo
// codigo se copio en TallerOrdenPos::facturar(), que quedo con el mismo bug
// (ver tarea aparte).

test('facturar una orden con un item manual (sin producto) y medio de pago no estandar no revienta', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa servicio tecnico facturar test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'servicio_tecnico',
        'usa_servicio_tecnico' => true,
    ]);

    $orden = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'cliente_nombre' => 'Cliente de prueba',
        'marca' => 'Samsung',
        'modelo' => 'Galaxy A54',
        'imei_serial' => '123456789012345',
        'estado' => 'en_proceso',
    ]);

    ServicioTecnicoItem::create([
        'orden_id' => $orden->id,
        'producto_id' => null,
        'descripcion' => 'Cambio de pantalla',
        'cantidad' => 1,
        'precio_unitario' => 180000,
        'subtotal' => 180000,
        'tipo' => 'mano_obra',
        'costo_proveedor' => 0,
    ]);

    $this->actingAs($empresa);

    Livewire::test(ServicioTecnicoOrdenPos::class, ['ordenId' => $orden->id])
        ->set('tipoPago', 'contado')
        ->set('medioPago', 'tarjeta')
        ->call('facturar');

    $orden->refresh();

    expect($orden->estado)->toBe('entregado');
    expect($orden->factura_id)->not->toBeNull();

    $factura = Factura::find($orden->factura_id);

    expect($factura)->not->toBeNull();
    expect($factura->tipo_factura)->toBe('salida');
    expect($factura->estado_pago)->toBe('pagada');
    expect($factura->medio_pago)->toBe('otro');
    expect((float) $factura->total)->toBe(180000.0);

    $detalle = $factura->detalles->first();
    expect($detalle->producto_id)->toBe(0);
    expect($detalle->descripcion_larga)->toBe('Cambio de pantalla');
});
