<?php

use App\Livewire\CarritoVenta;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Product;
use App\Models\ServicioTecnicoOrden;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Servicio Tecnico integrado al POS real (mismo mecanismo que ya existia
// para Taller, ver CarritoVenta::$tallerOrdenId): la orden se crea desde el
// boton "Ingresar" del POS (aca simulado llamando directo el metodo, que es
// lo que dispara el evento 'crear-orden-servicio-tecnico' del JS), despues
// el carrito normal queda activo para esa orden, y facturar cierra la
// orden (factura_id + estado=entregado), igual que Taller.

function crearEmpresaSvcTecPosTest(): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'servicio_tecnico', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Celulares Test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'servicio_tecnico',
        'usa_servicio_tecnico' => true,
    ]);

    return $empresa;
}

test('crear una orden de servicio tecnico desde el POS activa el carrito para esa orden', function () {
    $empresa = crearEmpresaSvcTecPosTest();

    Livewire::actingAs($empresa)
        ->test(CarritoVenta::class)
        ->call(
            'crearOrdenServicioTecnicoDesdePos',
            'Cliente Prueba',
            '3001234567',
            'Samsung',
            'Galaxy A54',
            '123456789012345',
            'Negro',
            '1-2-3',
            'Pantalla rota',
            'Urgente'
        )
        ->assertSet('servicioTecnicoOrdenId', fn ($id) => $id !== null);

    $orden = ServicioTecnicoOrden::where('empresa_id', $empresa->id)->firstOrFail();

    expect($orden->cliente_nombre)->toBe('Cliente Prueba');
    expect($orden->marca)->toBe('Samsung');
    expect($orden->imei_serial)->toBe('123456789012345');
    expect($orden->clave_desbloqueo)->toBe('1-2-3');
    expect($orden->estado)->toBe('en_proceso');
});

test('sin una orden activa, no se puede agregar productos al carrito', function () {
    $empresa = crearEmpresaSvcTecPosTest();
    $empresa->syncRoles(['servicio_tecnico']);

    $producto = Product::create([
        'id_producto' => 9001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Cambio de pantalla',
        'precio_venta1' => 180000,
        'existencias' => 100,
    ]);

    Livewire::actingAs($empresa)
        ->test(CarritoVenta::class)
        ->call('agregarProducto', $producto->id_producto)
        ->assertDispatched('error');
});

test('facturar una orden de servicio tecnico creada desde el POS la cierra y la vincula a la factura', function () {
    $empresa = crearEmpresaSvcTecPosTest();

    $producto = Product::create([
        'id_producto' => 9002,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Cambio de pantalla',
        'precio_venta1' => 180000,
        'existencias' => 100,
        'iva_venta' => 0,
    ]);

    $componente = Livewire::actingAs($empresa)->test(CarritoVenta::class);

    $componente->call(
        'crearOrdenServicioTecnicoDesdePos',
        'Cliente Prueba',
        '3001234567',
        'Samsung',
        'Galaxy A54',
        '123456789012345',
        'Negro',
        '',
        'Pantalla rota',
        ''
    );

    $ordenId = $componente->get('servicioTecnicoOrdenId');
    expect($ordenId)->not->toBeNull();

    $componente->call('agregarProducto', $producto->id_producto)
        ->call('facturarConfirmada');

    $orden = ServicioTecnicoOrden::find($ordenId);

    expect($orden->estado)->toBe('entregado');
    expect($orden->factura_id)->not->toBeNull();

    $factura = Factura::find($orden->factura_id);
    expect($factura)->not->toBeNull();
    expect((float) $factura->total)->toBe(180000.0);

    $componente->assertSet('servicioTecnicoOrdenId', null);
});
