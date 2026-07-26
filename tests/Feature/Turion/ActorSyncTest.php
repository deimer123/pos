<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use App\Services\Turion\CatalogoImportador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// El seed compartido de tests/Pest.php solo inserta NIT (id 6) -- estos
// tests tambien necesitan Cedula de ciudadania (id 3), el tipo_documento_id
// que usan los clientes de persona natural.
beforeEach(function () {
    DB::table('tipos_documento')->insertOrIgnore(['id' => 3, 'nombre' => 'Cédula de ciudadanía']);
});

// Un cliente creado offline en Turion (Actor::create en CarritoVenta.php/
// HotelPanel.php) no se subia nunca, y "Sincronizar" borraba y reemplazaba
// TODA la tabla actors con el catalogo del droplet -- lo que significaba
// perder ese cliente para siempre. Estos tests cubren la proteccion
// (CatalogoImportador preserva los que no tienen servidor_id) y el nuevo
// endpoint de subida (PosSyncController::actorCrear()).
//
// servidor_id solo existe en el esquema de Turion (sqlite); se agrega a
// mano sobre la tabla de pruebas (mysql) porque ninguno de los dos
// depende del motor de base de datos, solo necesitan la columna.

beforeEach(function () {
    if (! Schema::hasColumn('actors', 'servidor_id')) {
        Schema::table('actors', function ($table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }
});

afterEach(function () {
    if (Schema::hasColumn('actors', 'servidor_id')) {
        Schema::table('actors', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }
});

function crearEmpresaActorTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa actor test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

// id_clip_pro fijo colisiona con lo que UserObserver auto-asigna (calcula
// el siguiente global) al crear CONSUMIDOR FINAL/PROVEEDOR DE PRUEBA para
// cada empresa nueva -- se calcula fresco en cada test en vez de un valor
// quemado.
function siguienteIdClipPro(): int
{
    return (int) Actor::max('id_clip_pro') + 1;
}

test('sembrar() preserva un actor local que todavia no se ha subido', function () {
    $empresa = crearEmpresaActorTest();

    $local = Actor::create([
        'id_clip_pro' => siguienteIdClipPro(),
        'empresa_id' => $empresa->id,
        'tipo' => 1,
        'tipo_documento_id' => 3,
        'nombre' => 'Cliente creado offline',
        'servidor_id' => null,
    ]);

    CatalogoImportador::sembrar([
        'actors' => [], // el droplet no tiene (ni puede tener) este cliente todavia
    ]);

    expect(Actor::find($local->id))->not->toBeNull();
});

test('sembrar() SI reemplaza un actor que ya estaba sincronizado (con servidor_id)', function () {
    $empresa = crearEmpresaActorTest();

    $sincronizado = Actor::create([
        'id_clip_pro' => siguienteIdClipPro(),
        'empresa_id' => $empresa->id,
        'tipo' => 1,
        'tipo_documento_id' => 3,
        'nombre' => 'Ya sincronizado',
        'servidor_id' => 555,
    ]);

    CatalogoImportador::sembrar([
        'actors' => [],
    ]);

    expect(Actor::find($sincronizado->id))->toBeNull();
});

test('actorCrear() crea un cliente nuevo cuando no existe ninguno igual', function () {
    $empresa = crearEmpresaActorTest();
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $respuesta = $this->postJson('/api/pairing/subir/actor/crear', [
        'uuid' => (string) Str::uuid(),
        'actor_local_id' => 1,
        'identificacion' => '5551234',
        'nombre' => 'Cliente Turion Nuevo',
        'tipo_documento_id' => 3,
    ]);

    $respuesta->assertOk();

    $actor = Actor::findOrFail($respuesta->json('id'));
    expect($actor->nombre)->toBe('Cliente Turion Nuevo');
    expect($actor->identificacion)->toBe('5551234');
});

test('actorCrear() encuentra un cliente existente por identificacion en vez de duplicarlo', function () {
    $empresa = crearEmpresaActorTest();
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $existente = Actor::create([
        'id_clip_pro' => siguienteIdClipPro(),
        'empresa_id' => $empresa->id,
        'tipo' => 1,
        'tipo_documento_id' => 3,
        'identificacion' => '999888',
        'nombre' => 'Cliente Ya Existente',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/actor/crear', [
        'uuid' => (string) Str::uuid(),
        'actor_local_id' => 2,
        'identificacion' => '999888',
        'nombre' => 'Cliente Ya Existente',
    ]);

    $respuesta->assertOk();
    expect($respuesta->json('id'))->toBe($existente->id);
    expect(Actor::where('identificacion', '999888')->count())->toBe(1);
});

test('actorCrear() es idempotente por uuid', function () {
    $empresa = crearEmpresaActorTest();
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $payload = [
        'uuid' => (string) Str::uuid(),
        'actor_local_id' => 3,
        'nombre' => 'Cliente Idempotente',
    ];

    $primera = $this->postJson('/api/pairing/subir/actor/crear', $payload)->assertOk();
    $segunda = $this->postJson('/api/pairing/subir/actor/crear', $payload)->assertOk();

    expect($segunda->json('id'))->toBe($primera->json('id'));
    expect(Actor::where('nombre', 'Cliente Idempotente')->count())->toBe(1);
});
