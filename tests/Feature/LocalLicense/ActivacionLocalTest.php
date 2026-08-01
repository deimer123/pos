<?php

use App\Models\ActivacionLocal;
use App\Models\User;
use App\Services\LocalLicense\CodigoActivacion;
use App\Services\LocalLicense\FirmadorLicencia;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Flujo de activacion de la edicion Local: EnsureLicenciaLocalActiva
// redirige a /activar si no hay ActivacionLocal; ActivarLicenciaController
// valida el codigo offline (ValidadorLicencia) y crea el primer usuario
// admin_empresa. activacion_local solo existe en sqlite -- se simula el
// driver y se crea la tabla a mano, igual que los demas tests de Turion
// (ver tests/Feature/Turion/PosPushIdResueltoTest.php).
//
// A diferencia de esos tests (que solo corren comandos artisan), estos
// pasan por el stack completo de middleware 'web' via una request HTTP de
// verdad -- eso hace que ademas de DB::getDriverName() tambien se llame
// Schema::hasTable() dentro del mismo request (EnsureLicenciaLocalActiva).
// Mockear/reemplazar el manager entero (DB::swap()/DB::partialMock()) para
// eso rompe la resolucion de Schema::hasTable(), asi que en vez de eso se
// muta directo el config de la conexion REAL ya resuelta para que
// getDriverName() reporte 'sqlite' -- ningun mock, ninguna conexion nueva.
//
// OJO: Schema::create()/dropIfExists() (DDL) hacen COMMIT IMPLICITO en
// MySQL/InnoDB -- apenas prepararTablaActivacionLocal() corre, la
// transaccion que RefreshDatabase iba a revertir sola ya quedo confirmada,
// y todo lo que se cree despues (Role, User) sobrevive al test. Se limpia
// a mano en afterEach() en vez de confiar en el rollback automatico.
function simularEdicionLocal(): void
{
    $connection = DB::connection();
    $prop = new ReflectionProperty($connection, 'config');
    $prop->setAccessible(true);
    $config = $prop->getValue($connection);
    $config['driver'] = 'sqlite';
    $prop->setValue($connection, $config);

    Config::set('pos.edition', 'local');
}

afterEach(function () {
    Schema::dropIfExists('activacion_local');

    $emails = ['juan@ferreteria.test', 'juan2@ferreteria.test'];
    $ids = User::whereIn('email', $emails)->pluck('id');

    if ($ids->isNotEmpty()) {
        DB::table('model_has_roles')->whereIn('model_id', $ids)->where('model_type', User::class)->delete();
        User::whereIn('id', $ids)->delete();
    }

    DB::table('roles')->where('name', 'admin_empresa')->where('guard_name', 'web')->delete();
});

function prepararTablaActivacionLocal(): void
{
    Schema::dropIfExists('activacion_local');
    Schema::create('activacion_local', function ($table) {
        $table->id();
        $table->uuid('licencia_id');
        $table->unsignedBigInteger('empresa_id');
        $table->string('empresa_nombre');
        $table->string('machine_id');
        $table->text('codigo_raw');
        $table->timestamp('activada_at');
        $table->timestamps();
    });
}

function prepararLlavesActivacionTest(): void
{
    $keypair = sodium_crypto_sign_keypair();

    Config::set('pos.license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
    Config::set('pos.license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
}

function codigoValidoParaTest(string $machineId): string
{
    $codigo = new CodigoActivacion(
        licenciaId: (string) Str::uuid(),
        empresaId: 42,
        empresaNombre: 'Ferreteria La Llave',
        machineId: $machineId,
        issuedAt: new DateTimeImmutable(),
    );

    return (new FirmadorLicencia())->firmar($codigo);
}

test('sin activar, cualquier ruta redirige a /activar', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $this->get('/login')->assertRedirect(route('activar'));
});

test('la pantalla de activacion muestra el machine_id actual', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $this->get('/activar')->assertOk()->assertSee('MID-TEST-0001');
});

test('un codigo valido para esta maquina lleva al wizard de usuario admin', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();
    prepararLlavesActivacionTest();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $codigo = codigoValidoParaTest('MID-TEST-0001');

    $this->post('/activar', ['codigo' => $codigo])
        ->assertOk()
        ->assertSee('Ferreteria La Llave');
});

test('un codigo para otra maquina se rechaza con un mensaje claro', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();
    prepararLlavesActivacionTest();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $codigo = codigoValidoParaTest('MID-OTRA-9999');

    $this->post('/activar', ['codigo' => $codigo])
        ->assertRedirect()
        ->assertSessionHasErrors('codigo');
});

test('completar el wizard crea el usuario admin, activa la instalacion y entra a /admin', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    prepararTablaActivacionLocal();
    simularEdicionLocal();
    prepararLlavesActivacionTest();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $codigo = codigoValidoParaTest('MID-TEST-0001');

    $this->post('/activar/empresa', [
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 42,
        'empresa_nombre' => 'Ferreteria La Llave',
        'machine_id' => 'MID-TEST-0001',
        'codigo_raw' => $codigo,
        'admin_nombre' => 'Juan Perez',
        'admin_email' => 'juan@ferreteria.test',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ])->assertRedirect('/admin');

    $usuario = User::where('email', 'juan@ferreteria.test')->first();

    expect($usuario)->not->toBeNull();
    expect($usuario->hasRole('admin_empresa'))->toBeTrue();
    expect($usuario->activo)->toBeTrue();
    expect($usuario->plan_ends_at)->toBeNull();

    expect(ActivacionLocal::activa())->toBeTrue();
    $this->assertAuthenticatedAs($usuario);
});

test('un intento de guardarEmpresa con un codigo manipulado se rechaza sin crear nada', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();
    prepararLlavesActivacionTest();
    Config::set('pos.machine_id', 'MID-TEST-0001');

    $this->post('/activar/empresa', [
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 42,
        'empresa_nombre' => 'Ferreteria La Llave',
        'machine_id' => 'MID-TEST-0001',
        'codigo_raw' => 'POSLOC1-esto.noesvalido',
        'admin_nombre' => 'Juan Perez',
        'admin_email' => 'juan2@ferreteria.test',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ])->assertRedirect(route('activar'));

    expect(User::where('email', 'juan2@ferreteria.test')->exists())->toBeFalse();
    expect(ActivacionLocal::activa())->toBeFalse();
});

test('ya activada, /activar redirige al login', function () {
    prepararTablaActivacionLocal();
    simularEdicionLocal();

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 1,
        'empresa_nombre' => 'Ya activada',
        'machine_id' => 'MID-TEST-0001',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    $this->get('/activar')->assertRedirect('/login');
});
