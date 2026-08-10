<?php

use App\Livewire\CarritoVenta;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Hotel: no tenia ningun test directo del flujo real de "cargar la reserva
// en el POS -> agregar consumos -> facturar (checkout)" (solo Kardex de
// habitacion y sincronizacion Turion). Cubre el camino de dinero real.

function crearEmpresaHotelTest(): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Hotel de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'hotel',
        'usa_hotel' => true,
    ]);

    return $empresa;
}

test('cargar una reserva de hotel en el POS, agregar un consumo y facturar hace el checkout', function () {
    $empresa = crearEmpresaHotelTest();

    $habitacion = HotelHabitacion::create([
        'empresa_id' => $empresa->id,
        'numero' => '101',
        'camas_dobles' => 1,
        'camas_sencillas' => 0,
        'activa' => true,
    ]);

    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id,
        'habitacion_id' => $habitacion->id,
        'huesped_nombre' => 'Huesped de prueba',
        'numero_personas' => 1,
        'fecha_checkin' => now()->toDateString(),
        'fecha_checkout' => now()->addDay()->toDateString(),
        'precio_noche' => 150000,
        'estado' => 'checkin',
        'checkin_real_at' => now(),
        'creado_por' => $empresa->id,
    ]);

    $producto = Product::create([
        'id_producto' => 8001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Agua embotellada',
        'precio_venta1' => 4000,
        'existencias' => 100,
        'iva_venta' => 0,
    ]);

    $componente = Livewire::actingAs($empresa)->test(CarritoVenta::class);
    $componente->call('cargarHotelReserva', $reserva->id);
    $componente->assertSet('hotelReservaId', $reserva->id);

    $componente->call('agregarProducto', $producto->id_producto);
    $componente->call('facturarConfirmada');

    $reserva->refresh();
    expect($reserva->estado)->toBe('checkout');
    expect($reserva->factura_id)->not->toBeNull();

    $factura = Factura::find($reserva->factura_id);
    expect($factura)->not->toBeNull();
    expect((float) $factura->total)->toBeGreaterThanOrEqual(4000.0);
});
