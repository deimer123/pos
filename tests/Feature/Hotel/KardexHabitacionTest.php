<?php

use App\Filament\Pages\KardexHabitacion;
use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\HotelReservaConsumo;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Historial de productos agregados a la cuenta de cada habitación durante
// sus estadías (ver App\Filament\Pages\KardexHabitacion), distinto del
// Kardex de productos que mueve stock agregado de toda la empresa.

function crearEmpresaConHotelKardexTest(bool $usaHotel = true): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Hotel test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'hotel',
        'usa_hotel' => $usaHotel,
    ]);

    return $empresa;
}

test('canAccess() es falso si la empresa no tiene usa_hotel activo', function () {
    $empresa = crearEmpresaConHotelKardexTest(usaHotel: false);
    $this->actingAs($empresa);

    expect(KardexHabitacion::canAccess())->toBeFalse();
});

test('canAccess() es verdadero para admin_empresa con usa_hotel activo', function () {
    $empresa = crearEmpresaConHotelKardexTest();
    $this->actingAs($empresa);

    expect(KardexHabitacion::canAccess())->toBeTrue();
});

test('muestra las reservas de la habitacion seleccionada con sus consumos y totales', function () {
    $empresa = crearEmpresaConHotelKardexTest();
    $this->actingAs($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '101']);
    $otraHabitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '102']);

    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 1,
        'huesped_nombre' => 'Pepito Perez', 'fecha_checkin' => now()->subDay()->toDateString(),
        'fecha_checkout' => now()->addDay()->toDateString(), 'precio_noche' => 50000,
        'abono_monto' => 20000, 'estado' => 'checkin',
    ]);

    HotelReservaConsumo::create([
        'reserva_id' => $reserva->id, 'descripcion' => 'Gaseosa', 'cantidad' => 2,
        'precio_unitario' => 3000, 'subtotal' => 6000,
    ]);

    // De otra habitación: no debe aparecer al filtrar por $habitacion.
    $reservaOtra = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $otraHabitacion->id, 'numero_reserva' => 2,
        'huesped_nombre' => 'Otro huesped', 'fecha_checkin' => now()->toDateString(),
        'precio_noche' => 40000, 'estado' => 'checkin',
    ]);

    // Cancelada: no debe aparecer aunque sea de la misma habitacion.
    HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 3,
        'huesped_nombre' => 'Cancelado', 'fecha_checkin' => now()->toDateString(),
        'precio_noche' => 40000, 'estado' => 'cancelada',
    ]);

    Livewire::test(KardexHabitacion::class)
        ->set('habitacionId', $habitacion->id)
        ->set('desde', now()->subDays(3)->toDateString())
        ->set('hasta', now()->addDays(3)->toDateString())
        ->assertOk()
        ->assertSee('Pepito Perez')
        ->assertSee('Gaseosa')
        ->assertDontSee('Otro huesped')
        ->assertDontSee('Cancelado');

    expect($reserva->fresh()->total_consumos)->toBe(6000.0);
    expect($reserva->fresh()->total_estimado)->toBe(100000.0);
    expect($reserva->fresh()->saldo_pendiente)->toBe(86000.0);
});

test('una habitacion sin estadias en el rango muestra el mensaje vacio', function () {
    $empresa = crearEmpresaConHotelKardexTest();
    $this->actingAs($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '201']);

    Livewire::test(KardexHabitacion::class)
        ->set('habitacionId', $habitacion->id)
        ->assertOk()
        ->assertSee('no tuvo estadías en el rango seleccionado');
});

test('una reserva facturada sin fecha de salida planeada muestra el checkout real, no "Sin definir"', function () {
    $empresa = crearEmpresaConHotelKardexTest();
    $this->actingAs($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '401']);

    $checkoutReal = now()->subDay();

    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 1,
        'huesped_nombre' => 'Consumidor Final', 'fecha_checkin' => now()->subDays(2)->toDateString(),
        'fecha_checkout' => null, 'precio_noche' => 50000, 'estado' => 'checkout',
        'checkout_real_at' => $checkoutReal,
    ]);

    Livewire::test(KardexHabitacion::class)
        ->set('habitacionId', $habitacion->id)
        ->set('desde', now()->subDays(5)->toDateString())
        ->set('hasta', now()->addDays(5)->toDateString())
        ->assertOk()
        ->assertSee($checkoutReal->format('d/m/Y'))
        ->assertDontSee('Sin definir');
});
