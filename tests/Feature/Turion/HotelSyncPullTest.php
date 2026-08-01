<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\User;
use App\Services\Turion\HotelSyncPull;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

// HotelSyncPull::fusionar() es el lado de Turion que recibe la lista de
// reservas de hotel activas del droplet (PairingController::reservasHotel())
// y la mezcla con lo que ya existe localmente. Ver TallerSyncPullTest para
// el porque de agregar servidor_id a mano sobre la tabla de pruebas (mysql).

beforeEach(function () {
    if (! Schema::hasColumn('hotel_reservas', 'servidor_id')) {
        Schema::table('hotel_reservas', function ($table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    // Schema::table()->addColumn/dropColumn (arriba/abajo) hace commit
    // implicito en MySQL -- el User (y todo lo que UserObserver siembra
    // para el) que cada test crea despues sobrevive al rollback automatico
    // de RefreshDatabase. Se limpia a mano en afterEach (ver tests/Pest.php).
    $this->marcaLimpieza = marcarAltaLimpiezaManual();
});

afterEach(function () {
    if (Schema::hasColumn('hotel_reservas', 'servidor_id')) {
        Schema::table('hotel_reservas', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }

    limpiarTrasCommitImplicito($this->marcaLimpieza);
});

function crearEmpresaHotelPullTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa hotel pull test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

function crearHabitacionHotelPullTest(User $empresa): HotelHabitacion
{
    return HotelHabitacion::create([
        'empresa_id' => $empresa->id,
        'numero' => '101',
        'camas_dobles' => 1,
        'camas_sencillas' => 0,
    ]);
}

test('una reserva de hotel remota nueva se crea localmente con su servidor_id', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);

    HotelSyncPull::fusionar([
        [
            'id' => 801,
            'habitacion_id' => $habitacion->id,
            'actor_id' => null,
            'numero_personas' => 2,
            'climatizacion' => 'aire',
            'huesped_nombre' => 'Huesped Remoto',
            'huesped_telefono' => '3001112233',
            'huesped_documento' => null,
            'fecha_checkin' => now()->toDateString(),
            'fecha_checkout' => null,
            'checkin_real_at' => now()->toDateTimeString(),
            'precio_noche' => 80000,
            'abono_monto' => 20000,
            'abono_medio_pago' => 'efectivo',
            'estado' => 'checkin',
            'observaciones' => null,
            'items' => [
                ['producto_id' => null, 'nombre' => 'Gaseosa', 'cantidad' => 2, 'precio' => 3000],
            ],
        ],
    ], $empresa->id);

    $reserva = HotelReserva::where('empresa_id', $empresa->id)->where('servidor_id', 801)->first();

    expect($reserva)->not->toBeNull();
    expect($reserva->huesped_nombre)->toBe('Huesped Remoto');
    expect($reserva->estado)->toBe('checkin');
    expect($reserva->consumos)->toHaveCount(1);
    expect((float) $reserva->consumos->first()->subtotal)->toBe(6000.0);
});

test('una reserva remota ya bajada antes se actualiza, no se duplica', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);

    $payload = fn (string $estado) => [[
        'id' => 802,
        'habitacion_id' => $habitacion->id,
        'actor_id' => null,
        'numero_personas' => 1,
        'climatizacion' => null,
        'huesped_nombre' => 'Huesped',
        'huesped_telefono' => null, 'huesped_documento' => null,
        'fecha_checkin' => now()->toDateString(),
        'fecha_checkout' => null, 'checkin_real_at' => null,
        'precio_noche' => 50000, 'abono_monto' => 0, 'abono_medio_pago' => null,
        'estado' => $estado,
        'observaciones' => null,
        'items' => [],
    ]];

    HotelSyncPull::fusionar($payload('reservada'), $empresa->id);
    HotelSyncPull::fusionar($payload('checkin'), $empresa->id);

    expect(HotelReserva::where('empresa_id', $empresa->id)->where('servidor_id', 802)->count())->toBe(1);
    expect(HotelReserva::where('empresa_id', $empresa->id)->where('servidor_id', 802)->first()->estado)->toBe('checkin');
});

test('una reserva local con servidor_id que ya no viene en la lista remota se borra', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);

    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 999,
        'habitacion_id' => $habitacion->id,
        'numero_reserva' => 1,
        'huesped_nombre' => 'Ya hizo checkout en el droplet',
        'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkin',
    ]);

    HotelSyncPull::fusionar([], $empresa->id);

    expect(HotelReserva::find($reserva->id))->toBeNull();
});

test('una reserva local creada offline (sin servidor_id) no se toca', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);

    $local = HotelReserva::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'habitacion_id' => $habitacion->id,
        'numero_reserva' => 1,
        'huesped_nombre' => 'Creada offline, aun no subida',
        'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkin',
    ]);

    HotelSyncPull::fusionar([], $empresa->id);

    expect(HotelReserva::find($local->id))->not->toBeNull();
});

test('una reserva remota con habitacion_id que no existe localmente no tumba la fusion', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);

    HotelSyncPull::fusionar([
        [
            'id' => 803,
            'habitacion_id' => 999999,
            'actor_id' => null,
            'numero_personas' => 1,
            'climatizacion' => null,
            'huesped_nombre' => 'Habitacion inexistente',
            'huesped_telefono' => null, 'huesped_documento' => null,
            'fecha_checkin' => now()->toDateString(),
            'fecha_checkout' => null, 'checkin_real_at' => null,
            'precio_noche' => 50000, 'abono_monto' => 0, 'abono_medio_pago' => null,
            'estado' => 'checkin',
            'observaciones' => null,
            'items' => [],
        ],
        [
            'id' => 804,
            'habitacion_id' => $habitacion->id,
            'actor_id' => null,
            'numero_personas' => 1,
            'climatizacion' => null,
            'huesped_nombre' => 'Habitacion valida',
            'huesped_telefono' => null, 'huesped_documento' => null,
            'fecha_checkin' => now()->toDateString(),
            'fecha_checkout' => null, 'checkin_real_at' => null,
            'precio_noche' => 50000, 'abono_monto' => 0, 'abono_medio_pago' => null,
            'estado' => 'checkin',
            'observaciones' => null,
            'items' => [],
        ],
    ], $empresa->id);

    expect(HotelReserva::where('empresa_id', $empresa->id)->where('servidor_id', 803)->exists())->toBeFalse();
    expect(HotelReserva::where('empresa_id', $empresa->id)->where('servidor_id', 804)->exists())->toBeTrue();
});

test('PairingController::reservasHotel() solo devuelve las activas, con sus items', function () {
    $empresa = crearEmpresaHotelPullTest();
    $habitacion = crearHabitacionHotelPullTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = HotelReserva::create([
        'empresa_id' => $empresa->id,
        'habitacion_id' => $habitacion->id,
        'numero_reserva' => 1,
        'huesped_nombre' => 'Activa',
        'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkin',
    ]);
    $activa->consumos()->create([
        'producto_id' => null, 'descripcion' => 'Item', 'cantidad' => 1,
        'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);
    HotelReserva::create([
        'empresa_id' => $empresa->id,
        'habitacion_id' => $habitacion->id,
        'numero_reserva' => 2,
        'huesped_nombre' => 'Ya hizo checkout',
        'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkout',
    ]);

    $respuesta = $this->getJson('/api/pairing/reservas-hotel')->assertOk();

    $reservas = $respuesta->json('reservas');

    expect($reservas)->toHaveCount(1);
    expect($reservas[0]['id'])->toBe($activa->id);
    expect($reservas[0]['items'])->toHaveCount(1);
});
