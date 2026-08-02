<?php

use App\Models\ActivacionLocal;
use App\Models\User;
use App\Services\LocalLicense\CodigoActivacion;
use App\Services\LocalLicense\FirmadorLicencia;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Sumar un terminal (cliente) a un servidor de la edicion Local ya
// activado -- ver App\Http\Controllers\EmparejarTerminalLocalController.
// Mismo patron de simulacion de driver/tabla que ActivacionLocalTest.php
// (Schema::create()/dropIfExists() hacen commit implicito en MySQL, se
// limpia a mano en afterEach()).

function simularEdicionLocalTerminal(): void
{
    $connection = DB::connection();
    $prop = new ReflectionProperty($connection, 'config');
    $prop->setAccessible(true);
    $config = $prop->getValue($connection);
    $config['driver'] = 'sqlite';
    $prop->setValue($connection, $config);

    Config::set('pos.edition', 'local');
}

function prepararTablaActivacionLocalTerminal(): void
{
    Schema::dropIfExists('activacion_local');
    Schema::create('activacion_local', function ($table) {
        $table->id();
        $table->uuid('licencia_id');
        $table->unsignedBigInteger('empresa_id');
        $table->string('empresa_nombre');
        $table->string('machine_id');
        $table->string('rol', 20)->default('servidor');
        $table->text('codigo_raw');
        $table->timestamp('activada_at');
        $table->timestamps();
    });
}

function prepararLlavesTerminalTest(): void
{
    $keypair = sodium_crypto_sign_keypair();

    Config::set('pos.license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
    Config::set('pos.license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
}

function crearServidorActivado(int $empresaId = 42, string $empresaNombre = 'Ferreteria La Llave'): void
{
    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => $empresaId,
        'empresa_nombre' => $empresaNombre,
        'machine_id' => 'MID-SERVIDOR-0001',
        'rol' => 'servidor',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);
}

function codigoTerminalFirmado(string $machineId, int $empresaId = 42, string $rol = 'cliente'): string
{
    $codigo = new CodigoActivacion(
        licenciaId: (string) Str::uuid(),
        empresaId: $empresaId,
        empresaNombre: 'Ferreteria La Llave',
        machineId: $machineId,
        issuedAt: new DateTimeImmutable(),
        rol: $rol,
    );

    return (new FirmadorLicencia())->firmar($codigo);
}

afterEach(function () {
    Schema::dropIfExists('activacion_local');
});

test('GET sin terminal registrado muestra el formulario con el machine_id', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    crearServidorActivado();

    $this->get('/emparejar-terminal?machine_id=MID-TERMINAL-0001')
        ->assertOk()
        ->assertSee('MID-TERMINAL-0001');
});

test('GET con un terminal ya registrado redirige directo al login', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    crearServidorActivado();

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 42,
        'empresa_nombre' => 'Ferreteria La Llave',
        'machine_id' => 'MID-TERMINAL-0001',
        'rol' => 'cliente',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    $this->get('/emparejar-terminal?machine_id=MID-TERMINAL-0001')->assertRedirect('/login');
});

test('un codigo de rol cliente valido crea la fila del terminal y va al login', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    prepararLlavesTerminalTest();
    crearServidorActivado();

    $codigo = codigoTerminalFirmado('MID-TERMINAL-0001');

    $this->post('/emparejar-terminal', [
        'codigo' => $codigo,
        'machine_id' => 'MID-TERMINAL-0001',
    ])->assertRedirect('/login');

    $fila = ActivacionLocal::where('machine_id', 'MID-TERMINAL-0001')->first();

    expect($fila)->not->toBeNull();
    expect($fila->rol)->toBe('cliente');
    expect($fila->empresa_id)->toBe(42);
});

test('un codigo de rol servidor se rechaza en /emparejar-terminal', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    prepararLlavesTerminalTest();
    crearServidorActivado();

    $codigo = codigoTerminalFirmado('MID-TERMINAL-0001', rol: 'servidor');

    $this->post('/emparejar-terminal', [
        'codigo' => $codigo,
        'machine_id' => 'MID-TERMINAL-0001',
    ])->assertRedirect()->assertSessionHasErrors('codigo');

    expect(ActivacionLocal::where('machine_id', 'MID-TERMINAL-0001')->exists())->toBeFalse();
});

test('un codigo de otra empresa se rechaza aunque la firma sea valida', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    prepararLlavesTerminalTest();
    crearServidorActivado(empresaId: 42);

    $codigo = codigoTerminalFirmado('MID-TERMINAL-0001', empresaId: 99);

    $this->post('/emparejar-terminal', [
        'codigo' => $codigo,
        'machine_id' => 'MID-TERMINAL-0001',
    ])->assertRedirect()->assertSessionHasErrors('codigo');

    expect(ActivacionLocal::where('machine_id', 'MID-TERMINAL-0001')->exists())->toBeFalse();
});

test('sin servidor activado todavia, emparejar un terminal se rechaza con un mensaje claro', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    prepararLlavesTerminalTest();

    $codigo = codigoTerminalFirmado('MID-TERMINAL-0001');

    $this->post('/emparejar-terminal', [
        'codigo' => $codigo,
        'machine_id' => 'MID-TERMINAL-0001',
    ])->assertRedirect()->assertSessionHasErrors('codigo');

    expect(ActivacionLocal::where('machine_id', 'MID-TERMINAL-0001')->exists())->toBeFalse();
});

test('reenviar el mismo terminal ya registrado no duplica la fila', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();
    prepararLlavesTerminalTest();
    crearServidorActivado();

    $codigo = codigoTerminalFirmado('MID-TERMINAL-0001');

    $this->post('/emparejar-terminal', ['codigo' => $codigo, 'machine_id' => 'MID-TERMINAL-0001'])
        ->assertRedirect('/login');

    $this->post('/emparejar-terminal', ['codigo' => $codigo, 'machine_id' => 'MID-TERMINAL-0001'])
        ->assertRedirect('/login');

    expect(ActivacionLocal::where('machine_id', 'MID-TERMINAL-0001')->count())->toBe(1);
});

test('EnsureLicenciaLocalActiva deja pasar /emparejar-terminal aunque el servidor no este activado', function () {
    prepararTablaActivacionLocalTerminal();
    simularEdicionLocalTerminal();

    $this->get('/emparejar-terminal?machine_id=MID-TERMINAL-0001')->assertOk();
});
