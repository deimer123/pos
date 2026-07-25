<?php

use App\Services\Turion\ConectividadDroplet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// La suite corre contra MySQL real, asi que "sync_state" (que solo existe
// en el sqlite de una terminal de Turion) no existe aqui -- se crea/borra
// a mano en cada test que necesita simular una terminal ya (o no)
// emparejada. Nota: Schema::create/dropIfExists hacen commit implicito en
// MySQL, asi que estos tests no dejan nada de negocio a medio insertar
// para que el rollback de RefreshDatabase tenga algo que deshacer.

// DB::partialMock() (el helper de Facade) genera un mock del NOMBRE de
// clase, sin pasar por el constructor real -- cualquier metodo no
// explicitamente esperado revienta porque $this->app quedo null en el
// mock. Aqui se envuelve la INSTANCIA real ya resuelta (Mockery preserva
// su estado interno), asi que solo getDriverName() queda fingido y
// DB::table(...) sigue funcionando de verdad contra la misma conexion.
function simularTurionConectividad(): void
{
    $real = app('db');
    $mock = Mockery::mock($real)->makePartial();
    $mock->shouldReceive('getDriverName')->andReturn('sqlite');
    // DB::swap() (no app()->instance()): actualiza tambien la cache
    // estatica interna del facade (Facade::$resolvedInstance), que si no
    // se toca sigue apuntando a la instancia real ya resuelta antes de
    // este test -- con solo app()->instance() el facade seguiria usando
    // la vieja.
    DB::swap($mock);
}

function crearSyncStateDePrueba(?string $token = 'token-de-prueba', ?string $servidor = 'https://droplet.test'): void
{
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

    DB::table('sync_state')->insert([
        'terminal_token' => $token,
        'servidor_url' => $servidor,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

afterEach(function () {
    Schema::dropIfExists('sync_state');
});

test('en el droplet (mysql) siempre esta en linea, sin llamar al droplet', function () {
    Http::fake();

    expect(ConectividadDroplet::estaEnLinea())->toBeTrue();
    Http::assertNothingSent();
});

test('en Turion sin emparejar (sin sync_state) no esta en linea', function () {
    simularTurionConectividad();
    Schema::dropIfExists('sync_state');

    expect(ConectividadDroplet::estaEnLinea())->toBeFalse();
});

test('en Turion emparejado y el droplet responde, esta en linea', function () {
    simularTurionConectividad();
    crearSyncStateDePrueba();

    Http::fake([
        'droplet.test/api/pairing/ping' => Http::response(['ok' => true], 200),
    ]);

    expect(ConectividadDroplet::estaEnLinea())->toBeTrue();
});

test('en Turion emparejado pero el droplet no responde, no esta en linea', function () {
    simularTurionConectividad();
    crearSyncStateDePrueba();

    Http::fake([
        'droplet.test/api/pairing/ping' => Http::response(null, 500),
    ]);

    expect(ConectividadDroplet::estaEnLinea())->toBeFalse();
});

test('en Turion emparejado pero sin red (excepcion de conexion), no esta en linea', function () {
    simularTurionConectividad();
    crearSyncStateDePrueba();

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('sin red');
    });

    expect(ConectividadDroplet::estaEnLinea())->toBeFalse();
});

test('llamar() sin terminal emparejada lanza una excepcion clara', function () {
    simularTurionConectividad();
    Schema::dropIfExists('sync_state');

    ConectividadDroplet::llamar('/api/pairing/subir/venta', ['carrito' => []]);
})->throws(RuntimeException::class, 'Esta terminal todavia no esta emparejada con el droplet.');

test('llamar() hace POST autenticado con el token guardado y devuelve el JSON', function () {
    simularTurionConectividad();
    crearSyncStateDePrueba(token: 'mi-token-secreto');

    Http::fake([
        'droplet.test/api/pairing/subir/venta' => Http::response(['id' => 42, 'numero_visual' => 'FV-42'], 200),
    ]);

    $resultado = ConectividadDroplet::llamar('/api/pairing/subir/venta', ['carrito' => [['id_producto' => 1]]]);

    expect($resultado)->toBe(['id' => 42, 'numero_visual' => 'FV-42']);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->url() === 'https://droplet.test/api/pairing/subir/venta'
            && $request->hasHeader('Authorization', 'Bearer mi-token-secreto')
            && $request['carrito'][0]['id_producto'] === 1
            && isset($request['uuid']);
    });
});

test('llamar() lanza excepcion con el mensaje del droplet si la respuesta falla', function () {
    simularTurionConectividad();
    crearSyncStateDePrueba();

    Http::fake([
        'droplet.test/api/pairing/subir/venta' => Http::response(['message' => 'Stock insuficiente'], 422),
    ]);

    ConectividadDroplet::llamar('/api/pairing/subir/venta', ['carrito' => []]);
})->throws(RuntimeException::class, 'Stock insuficiente');
