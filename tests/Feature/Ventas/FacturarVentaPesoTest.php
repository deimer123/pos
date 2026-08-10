<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\User;
use App\Services\Ventas\FacturarVentaService;

// Carniceria (venta por peso): no habia ningun test cubriendo una venta con
// cantidad decimal real (ej. 1.5 kg) de punta a punta -- valida que el
// stock se descuenta con la fraccion correcta (no redondeado a entero) y
// que el detalle de factura guarda la cantidad decimal tal cual.

test('facturar un producto vendido por peso descuenta la cantidad decimal exacta del stock', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Carnicería de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'carniceria',
        'usa_peso' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 6001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Carne de res x kg',
        'precio_venta1' => 18000,
        'precio_costo' => 12000,
        'existencias' => 20,
        'vende_por' => 'peso',
        'iva_venta' => 0,
    ]);

    $consumidorFinal = Actor::where('empresa_id', $empresa->id)->where('nombre', 'CONSUMIDOR FINAL')->first();

    $carrito = [
        (string) $producto->id_producto => [
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->descripcion_larga,
            'cantidad' => 1.5,
            'precio' => 18000,
            'nuevo_precio' => 18000,
            'descuento' => 0,
            'vende_por' => 'peso',
            'permite_decimal' => true,
        ],
    ];

    $factura = app(FacturarVentaService::class)->facturar($carrito, [
        'empresa_id' => $empresa->id,
        'cliente_id' => $consumidorFinal?->id,
        'user_id' => $empresa->id,
        'vendedor_id' => $empresa->id,
        'tipo_pago' => 'contado',
        'medio_pago' => 'efectivo',
    ]);

    expect((float) $producto->fresh()->existencias)->toBe(18.5);

    $detalle = $factura->detalles->first();
    expect((float) $detalle->cantidad)->toBe(1.5);
    expect((float) $factura->total)->toBe(27000.0);
});
