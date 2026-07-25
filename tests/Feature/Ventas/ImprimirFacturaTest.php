<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Regresion de las vistas de impresion: la tira POS y la version tamano
// carta comparten el mismo parcial de datos (facturas/_datos.blade.php) --
// verifica que ambas rendericen sin errores para la misma factura, y que
// el NIT de la empresa cliente (guardado siempre, no solo si activa
// facturacion electronica) aparezca en el ticket.

test('la factura se puede ver en tira POS y en tamano carta sin errores', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'nit' => '900123456',
    ]);

    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 99,
        'tipo' => 1,
        'tipo_documento_id' => 6,
        'identificacion' => '111222333',
        'nombre' => 'Cliente de prueba',
    ]);

    $factura = Factura::create([
        'empresa_id' => $empresa->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'total' => 50000,
        'saldo' => 0,
    ]);
    $factura->detalles()->create([
        'producto_id' => 0,
        'descripcion_larga' => 'Producto de prueba',
        'cantidad' => 1,
        'precio' => 50000,
        'subtotal' => 50000,
        'descuento' => 0,
    ]);

    $this->actingAs($empresa)
        ->get(route('factura.imprimir', $factura->id))
        ->assertOk()
        ->assertSee('900123456')
        ->assertSee('Cliente de prueba');

    $this->actingAs($empresa)
        ->get(route('factura.imprimir', ['id' => $factura->id, 'formato' => 'carta']))
        ->assertOk()
        ->assertSee('900123456')
        ->assertSee('Cliente de prueba');
});
