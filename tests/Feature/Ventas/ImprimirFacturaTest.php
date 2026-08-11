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

// Bug real encontrado en produccion: el ticket mostraba "Numero Factus:
// Pendiente" / "Estado: PENDIENTE" / "validada por Factus" en facturas
// validadas por el PROVEEDOR ALTERNO (UBL 2.1) -- porque las vistas solo
// leian factus_number/factus_status/factus_cufe. Ver
// App\Support\FacturaImpresionData, que ahora resuelve estos datos sin
// amarrarse a un proveedor en particular.
test('una factura validada por el proveedor alterno UBL 2.1 no menciona Factus ni sale como pendiente', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa UBL21 impresion test',
        'representante_legal' => 'Rep Legal',
        'nit' => '900123456',
        'factura_electronica_proveedor' => 'ubl21',
        'ubl21_prefix' => 'SETP',
        'ubl21_resolution_number' => '18760000001',
        'ubl21_numbering_from' => 990000000,
        'ubl21_numbering_to' => 995000000,
    ]);

    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 100,
        'tipo' => 1,
        'tipo_documento_id' => 6,
        'identificacion' => '111222333',
        'nombre' => 'Cliente de prueba',
    ]);

    $factura = Factura::create([
        'empresa_id' => $empresa->id,
        'cliente_id' => $cliente->id,
        'user_id' => $empresa->id,
        'tipo_factura' => 'electronica',
        'total' => 57000,
        'saldo' => 0,
        'ubl21_document_number' => 'SETP990000003',
        'ubl21_cufe' => 'cufe-fake-999',
        'ubl21_status' => 'validada',
        'ubl21_validated_at' => now(),
    ]);
    $factura->detalles()->create([
        'producto_id' => 0,
        'descripcion_larga' => 'Producto de prueba',
        'cantidad' => 1,
        'precio' => 57000,
        'subtotal' => 57000,
        'descuento' => 0,
    ]);

    $response = $this->actingAs($empresa)
        ->get(route('factura.imprimir', $factura->id))
        ->assertOk()
        ->assertSee('SETP990000003')
        ->assertSee('cufe-fake-999')
        ->assertSee('VALIDADA')
        ->assertDontSee('Pendiente')
        ->assertDontSee('PENDIENTE');

    $response->assertDontSee('Factus');
});
