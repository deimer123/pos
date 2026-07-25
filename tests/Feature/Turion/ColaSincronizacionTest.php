<?php

use App\Services\Turion\ColaSincronizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// servidorIdSincronizado() es el mecanismo que le permite a Turion saber
// si un registro creado/editado localmente (prefactura, orden de taller,
// reserva de hotel) ya tiene contraparte en el droplet -- lo usan tanto
// PosPush (para actualizar en vez de crear) como FacturarEnLineaService
// (para mandar el id correcto al facturar en linea).

function simularTurionCola(): void
{
    $real = app('db');
    $mock = Mockery::mock($real)->makePartial();
    $mock->shouldReceive('getDriverName')->andReturn('sqlite');
    DB::swap($mock);
}

function crearPendingSyncOperationsDePrueba(): void
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
}

function insertarOperacionDePrueba(string $tipo, string $estado, array $payload, ?int $resultadoId = null): void
{
    DB::table('pending_sync_operations')->insert([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'tipo' => $tipo,
        'payload' => json_encode($payload),
        'estado' => $estado,
        'resultado_id' => $resultadoId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

afterEach(function () {
    Schema::dropIfExists('pending_sync_operations');
});

test('en el droplet (mysql) siempre devuelve null, sin tocar la tabla', function () {
    expect(ColaSincronizacion::servidorIdSincronizado('prefactura_guardar', 'prefactura_local_id', 5))->toBeNull();
});

test('sin ninguna operacion sincronizada para ese local_id, devuelve null', function () {
    simularTurionCola();
    crearPendingSyncOperationsDePrueba();

    insertarOperacionDePrueba('prefactura_guardar', 'pendiente', ['prefactura_local_id' => 7]);

    expect(ColaSincronizacion::servidorIdSincronizado('prefactura_guardar', 'prefactura_local_id', 7))->toBeNull();
});

test('con una operacion ya sincronizada, devuelve su resultado_id', function () {
    simularTurionCola();
    crearPendingSyncOperationsDePrueba();

    insertarOperacionDePrueba('prefactura_guardar', 'sincronizado', ['prefactura_local_id' => 7], resultadoId: 42);

    expect(ColaSincronizacion::servidorIdSincronizado('prefactura_guardar', 'prefactura_local_id', 7))->toBe(42);
});

test('con varias sincronizadas para el mismo local_id, devuelve la mas reciente', function () {
    simularTurionCola();
    crearPendingSyncOperationsDePrueba();

    insertarOperacionDePrueba('prefactura_guardar', 'sincronizado', ['prefactura_local_id' => 7], resultadoId: 42);
    insertarOperacionDePrueba('prefactura_guardar', 'sincronizado', ['prefactura_local_id' => 7], resultadoId: 42);

    expect(ColaSincronizacion::servidorIdSincronizado('prefactura_guardar', 'prefactura_local_id', 7))->toBe(42);
});

test('no mezcla local_id de distintos tipos de operacion', function () {
    simularTurionCola();
    crearPendingSyncOperationsDePrueba();

    insertarOperacionDePrueba('taller_crear', 'sincronizado', ['local_id' => 7], resultadoId: 99);

    expect(ColaSincronizacion::servidorIdSincronizado('prefactura_guardar', 'prefactura_local_id', 7))->toBeNull();
    expect(ColaSincronizacion::servidorIdSincronizado('taller_crear', 'local_id', 7))->toBe(99);
});
