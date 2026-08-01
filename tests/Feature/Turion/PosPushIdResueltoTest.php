<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\TallerOrden;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// PosPush resolvia taller_orden_id/hotel_reserva_id LOCAL a su id real en
// el droplet escaneando pending_sync_operations en busca de un "_crear"
// que ya se hubiera subido EN ALGUNA corrida anterior -- pero una orden/
// reserva que llego por PULL (Sincronizar, ver TallerSyncPull/
// HotelSyncPull) nunca tuvo un "_crear" que subir, asi que ese escaneo
// jamas la encontraba, aunque su columna servidor_id SI estuviera puesta.
// Ahora se lee esa columna directo.
//
// sync_state/pending_sync_operations solo existen en sqlite (Turion) -- se
// crean a mano aqui y se fuerza el driver reportado, igual que en los
// demas tests de este paquete que ejercitan la cola local sobre la BD de
// pruebas (mysql). El mock del driver se activa DESPUES de crear los
// datos de prueba a proposito: si se activa antes, TallerOrden/
// HotelReserva::booted() encolarian su propio "taller_crear"/"hotel_crear"
// (piensan que estan corriendo en Turion de verdad) y esa operacion extra
// interferiria con la que el test quiere ejercitar.

function simularTurionPush(): void
{
    $real = app('db');
    $mock = Mockery::mock($real)->makePartial();
    $mock->shouldReceive('getDriverName')->andReturn('sqlite');
    DB::swap($mock);
}

function prepararTablasTurionPush(): void
{
    Schema::dropIfExists('pending_sync_operations');
    Schema::create('pending_sync_operations', function ($table) {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('tipo');
        $table->json('payload');
        $table->string('estado')->default('pendiente');
        $table->text('error')->nullable();
        $table->unsignedBigInteger('resultado_id')->nullable();
        $table->timestamp('sincronizado_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('sync_state');
    Schema::create('sync_state', function ($table) {
        $table->id();
        $table->unsignedBigInteger('empresa_id')->nullable();
        $table->string('empresa_nombre')->nullable();
        $table->text('terminal_token')->nullable();
        $table->string('servidor_url')->nullable();
        $table->timestamp('ultima_sincronizacion_at')->nullable();
        $table->timestamp('ultima_subida_at')->nullable();
        $table->timestamps();
    });
}

beforeEach(function () {
    if (! Schema::hasColumn('taller_ordenes', 'servidor_id')) {
        Schema::table('taller_ordenes', function ($table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    if (! Schema::hasColumn('hotel_reservas', 'servidor_id')) {
        Schema::table('hotel_reservas', function ($table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }

    // Todo el DDL de arriba (Schema::table()->addColumn) hace commit
    // implicito en MySQL -- el User (y todo lo que UserObserver siembra
    // para el) que cada test crea despues sobrevive al rollback automatico
    // de RefreshDatabase. Se limpia a mano en afterEach (ver tests/Pest.php).
    $this->marcaLimpieza = marcarAltaLimpiezaManual();
});

afterEach(function () {
    Schema::dropIfExists('pending_sync_operations');
    Schema::dropIfExists('sync_state');

    if (Schema::hasColumn('taller_ordenes', 'servidor_id')) {
        Schema::table('taller_ordenes', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }

    if (Schema::hasColumn('hotel_reservas', 'servidor_id')) {
        Schema::table('hotel_reservas', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }

    limpiarTrasCommitImplicito($this->marcaLimpieza);
});

function crearEmpresaPosPushTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa pos push test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

test('taller_item sube bien una orden que llego por Sincronizar (servidor_id en la fila, sin taller_crear en la cola)', function () {
    $empresa = crearEmpresaPosPushTest();

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 777,
        'numero_orden' => 1,
        'cliente_nombre' => 'Bajada por Sincronizar',
        'placa' => 'ABC123',
        'estado' => 'en_proceso',
    ]);

    simularTurionPush();
    prepararTablasTurionPush();

    DB::table('sync_state')->insert([
        'empresa_id' => $empresa->id,
        'terminal_token' => 'token-de-prueba',
        'servidor_url' => 'https://droplet.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pending_sync_operations')->insert([
        'uuid' => (string) Str::uuid(),
        'tipo' => 'taller_item',
        'payload' => json_encode(['taller_orden_id' => $orden->id, 'items' => []]),
        'estado' => 'pendiente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'droplet.test/*' => Http::response(['id' => $orden->id], 200),
    ]);

    $this->artisan('pos:push')->assertExitCode(0);

    $operacion = DB::table('pending_sync_operations')->first();
    expect($operacion->estado)->toBe('sincronizado');

    Http::assertSent(function ($request) {
        return $request['taller_orden_id'] === 777;
    });
});

test('hotel_item sube bien una reserva que llego por Sincronizar (servidor_id en la fila, sin hotel_crear en la cola)', function () {
    $empresa = crearEmpresaPosPushTest();
    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '101']);

    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 888,
        'habitacion_id' => $habitacion->id,
        'numero_reserva' => 1,
        'huesped_nombre' => 'Bajada por Sincronizar',
        'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkin',
    ]);

    simularTurionPush();
    prepararTablasTurionPush();

    DB::table('sync_state')->insert([
        'empresa_id' => $empresa->id,
        'terminal_token' => 'token-de-prueba',
        'servidor_url' => 'https://droplet.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pending_sync_operations')->insert([
        'uuid' => (string) Str::uuid(),
        'tipo' => 'hotel_item',
        'payload' => json_encode(['hotel_reserva_id' => $reserva->id, 'items' => []]),
        'estado' => 'pendiente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'droplet.test/*' => Http::response(['id' => $reserva->id], 200),
    ]);

    $this->artisan('pos:push')->assertExitCode(0);

    $operacion = DB::table('pending_sync_operations')->first();
    expect($operacion->estado)->toBe('sincronizado');

    Http::assertSent(function ($request) {
        return $request['hotel_reserva_id'] === 888;
    });
});

test('taller_item sigue fallando con un mensaje claro si la orden nunca se ha subido (sin servidor_id)', function () {
    $empresa = crearEmpresaPosPushTest();

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'numero_orden' => 1,
        'cliente_nombre' => 'Solo local, nunca subida',
        'placa' => 'XYZ999',
        'estado' => 'pendiente',
    ]);

    simularTurionPush();
    prepararTablasTurionPush();

    DB::table('sync_state')->insert([
        'empresa_id' => $empresa->id,
        'terminal_token' => 'token-de-prueba',
        'servidor_url' => 'https://droplet.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pending_sync_operations')->insert([
        'uuid' => (string) Str::uuid(),
        'tipo' => 'taller_item',
        'payload' => json_encode(['taller_orden_id' => $orden->id, 'items' => []]),
        'estado' => 'pendiente',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('pos:push');

    $operacion = DB::table('pending_sync_operations')->first();
    expect($operacion->estado)->toBe('error');
    expect($operacion->error)->toContain('Todavia no se ha subido la orden de taller local');
});
