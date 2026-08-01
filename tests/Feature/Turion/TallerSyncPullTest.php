<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\TallerOrden;
use App\Models\User;
use App\Services\Turion\TallerSyncPull;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

// TallerSyncPull::fusionar() es el lado de Turion que recibe la lista de
// ordenes de taller activas del droplet (PairingController::ordenesTaller())
// y la mezcla con lo que ya existe localmente -- sin esto, una orden creada
// directo en el droplet (o por otra terminal) nunca aparecia en Turion ni
// con "Sincronizar".
//
// servidor_id solo existe en el esquema de Turion (sqlite); aqui se agrega
// a mano sobre la tabla de pruebas (mysql) porque fusionar() no depende del
// motor de base de datos, solo necesita la columna.

beforeEach(function () {
    if (! Schema::hasColumn('taller_ordenes', 'servidor_id')) {
        Schema::table('taller_ordenes', function ($table) {
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
    if (Schema::hasColumn('taller_ordenes', 'servidor_id')) {
        Schema::table('taller_ordenes', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }

    limpiarTrasCommitImplicito($this->marcaLimpieza);
});

function crearEmpresaTallerPullTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa taller pull test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

test('una orden de taller remota nueva se crea localmente con su servidor_id', function () {
    $empresa = crearEmpresaTallerPullTest();

    TallerSyncPull::fusionar([
        [
            'id' => 701,
            'cliente_id' => null,
            'cliente_nombre' => 'Cliente Remoto',
            'cliente_telefono' => '3001234567',
            'placa' => 'ABC123',
            'marca' => 'Suzuki',
            'modelo' => 'AX-100',
            'color' => 'Rojo',
            'km_ingreso' => 1000,
            'diagnostico' => 'Revisión general',
            'observaciones' => null,
            'estado' => 'en_proceso',
            'fecha_entrega_estimada' => null,
            'items' => [
                ['producto_id' => null, 'nombre' => 'Cambio de aceite', 'cantidad' => 1, 'precio' => 30000],
            ],
        ],
    ], $empresa->id);

    $orden = TallerOrden::where('empresa_id', $empresa->id)->where('servidor_id', 701)->first();

    expect($orden)->not->toBeNull();
    expect($orden->placa)->toBe('ABC123');
    expect($orden->estado)->toBe('en_proceso');
    expect($orden->repuestos)->toHaveCount(1);
    expect((float) $orden->repuestos->first()->subtotal)->toBe(30000.0);
});

test('una orden de taller remota ya bajada antes se actualiza, no se duplica', function () {
    $empresa = crearEmpresaTallerPullTest();

    $payload = fn (string $estado) => [[
        'id' => 702,
        'cliente_id' => null,
        'cliente_nombre' => 'Cliente Remoto',
        'placa' => 'XYZ999',
        'marca' => null, 'modelo' => null, 'color' => null, 'km_ingreso' => null,
        'diagnostico' => null, 'observaciones' => null,
        'estado' => $estado,
        'fecha_entrega_estimada' => null,
        'items' => [],
    ]];

    TallerSyncPull::fusionar($payload('pendiente'), $empresa->id);
    TallerSyncPull::fusionar($payload('listo'), $empresa->id);

    expect(TallerOrden::where('empresa_id', $empresa->id)->where('servidor_id', 702)->count())->toBe(1);
    expect(TallerOrden::where('empresa_id', $empresa->id)->where('servidor_id', 702)->first()->estado)->toBe('listo');
});

test('una orden de taller local con servidor_id que ya no viene en la lista remota se borra', function () {
    $empresa = crearEmpresaTallerPullTest();

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 999,
        'numero_orden' => 1,
        'cliente_nombre' => 'Ya entregada en el droplet',
        'placa' => 'AAA111',
        'estado' => 'pendiente',
    ]);

    TallerSyncPull::fusionar([], $empresa->id);

    expect(TallerOrden::find($orden->id))->toBeNull();
});

test('una orden de taller local creada offline (sin servidor_id) no se toca', function () {
    $empresa = crearEmpresaTallerPullTest();

    $local = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'numero_orden' => 1,
        'cliente_nombre' => 'Creada offline, aun no subida',
        'placa' => 'BBB222',
        'estado' => 'pendiente',
    ]);

    TallerSyncPull::fusionar([], $empresa->id);

    expect(TallerOrden::find($local->id))->not->toBeNull();
});

test('numero_orden remoto que colisiona con uno local offline no rompe la fusion', function () {
    $empresa = crearEmpresaTallerPullTest();

    // Orden creada offline en esta misma terminal, todavia sin subir.
    TallerOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'numero_orden' => 1,
        'cliente_nombre' => 'Offline',
        'placa' => 'CCC333',
        'estado' => 'pendiente',
    ]);

    TallerSyncPull::fusionar([
        [
            'id' => 703,
            'cliente_id' => null,
            'cliente_nombre' => 'Remota',
            'placa' => 'DDD444',
            'marca' => null, 'modelo' => null, 'color' => null, 'km_ingreso' => null,
            'diagnostico' => null, 'observaciones' => null,
            'estado' => 'pendiente',
            'fecha_entrega_estimada' => null,
            'items' => [],
        ],
    ], $empresa->id);

    expect(TallerOrden::where('empresa_id', $empresa->id)->count())->toBe(2);
});

test('cliente_id inexistente localmente se guarda como null', function () {
    $empresa = crearEmpresaTallerPullTest();

    TallerSyncPull::fusionar([
        [
            'id' => 704,
            'cliente_id' => 999999,
            'cliente_nombre' => 'Cliente',
            'placa' => 'EEE555',
            'marca' => null, 'modelo' => null, 'color' => null, 'km_ingreso' => null,
            'diagnostico' => null, 'observaciones' => null,
            'estado' => 'pendiente',
            'fecha_entrega_estimada' => null,
            'items' => [],
        ],
    ], $empresa->id);

    $orden = TallerOrden::where('empresa_id', $empresa->id)->where('servidor_id', 704)->first();

    expect($orden->cliente_id)->toBeNull();
});

test('PairingController::ordenesTaller() solo devuelve las activas, con sus items', function () {
    $empresa = crearEmpresaTallerPullTest();
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = TallerOrden::create([
        'empresa_id' => $empresa->id,
        'numero_orden' => 1,
        'cliente_nombre' => 'Activa',
        'placa' => 'FFF666',
        'estado' => 'en_proceso',
    ]);
    $activa->repuestos()->create([
        'producto_id' => null, 'descripcion' => 'Item', 'cantidad' => 1,
        'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);
    TallerOrden::create([
        'empresa_id' => $empresa->id,
        'numero_orden' => 2,
        'cliente_nombre' => 'Ya entregada',
        'placa' => 'GGG777',
        'estado' => 'entregado',
    ]);

    $respuesta = $this->getJson('/api/pairing/ordenes-taller')->assertOk();

    $ordenes = $respuesta->json('ordenes');

    expect($ordenes)->toHaveCount(1);
    expect($ordenes[0]['id'])->toBe($activa->id);
    expect($ordenes[0]['items'])->toHaveCount(1);
});
