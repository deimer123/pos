<?php

use App\Exceptions\LocalLicense\LicenciaInvalidaException;
use App\Exceptions\LocalLicense\LicenciaOtraMaquinaException;
use App\Services\LocalLicense\CodigoActivacion;
use App\Services\LocalLicense\FirmadorLicencia;
use App\Services\LocalLicense\ValidadorLicencia;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

// Firma/verificacion offline de los codigos de activacion de la edicion
// Local (Ed25519): el droplet firma con FirmadorLicencia, el cliente
// verifica con ValidadorLicencia sin llamar nunca al droplet.

function prepararLlavesLicenciaTest(): void
{
    $keypair = sodium_crypto_sign_keypair();

    Config::set('pos.license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
    Config::set('pos.license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
    Config::set('pos.license.format_version', 1);
}

function codigoActivacionDeTest(string $machineId = 'MID-TEST-0001'): CodigoActivacion
{
    return new CodigoActivacion(
        licenciaId: (string) Str::uuid(),
        empresaId: 1,
        empresaNombre: 'Empresa de prueba',
        machineId: $machineId,
        issuedAt: new DateTimeImmutable(),
    );
}

test('firmar y verificar un codigo valido hace un viaje redondo correcto', function () {
    prepararLlavesLicenciaTest();

    $codigo = codigoActivacionDeTest();
    $codigoFirmado = (new FirmadorLicencia())->firmar($codigo);

    $resultado = (new ValidadorLicencia())->verificar($codigoFirmado, $codigo->machineId);

    expect($resultado->licenciaId)->toBe($codigo->licenciaId);
    expect($resultado->empresaId)->toBe($codigo->empresaId);
    expect($resultado->empresaNombre)->toBe($codigo->empresaNombre);
    expect($resultado->machineId)->toBe($codigo->machineId);
});

test('una firma corrupta (un caracter cambiado) se rechaza', function () {
    prepararLlavesLicenciaTest();

    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest());

    [$cuerpo, $firma] = explode('.', $codigoFirmado);
    $firmaCorrupta = ($firma[0] === 'A' ? 'B' : 'A').substr($firma, 1);

    expect(fn () => (new ValidadorLicencia())->verificar("{$cuerpo}.{$firmaCorrupta}", 'MID-TEST-0001'))
        ->toThrow(LicenciaInvalidaException::class);
});

test('un machine_id distinto al del codigo se rechaza con la excepcion especifica', function () {
    prepararLlavesLicenciaTest();

    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest('MID-AAAA-0001'));

    expect(fn () => (new ValidadorLicencia())->verificar($codigoFirmado, 'MID-BBBB-9999'))
        ->toThrow(LicenciaOtraMaquinaException::class);
});

test('una version de formato que esta instalacion no soporta se rechaza explicitamente', function () {
    prepararLlavesLicenciaTest();

    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest());
    $codigoVersionFutura = preg_replace('/^POSLOC\d+-/', 'POSLOC2-', $codigoFirmado);

    expect(fn () => (new ValidadorLicencia())->verificar($codigoVersionFutura, 'MID-TEST-0001'))
        ->toThrow(LicenciaInvalidaException::class);
});

test('un codigo truncado o mal formado se rechaza con un mensaje claro, no un error no controlado', function () {
    prepararLlavesLicenciaTest();

    expect(fn () => (new ValidadorLicencia())->verificar('esto-no-es-un-codigo', 'MID-TEST-0001'))
        ->toThrow(LicenciaInvalidaException::class);

    expect(fn () => (new ValidadorLicencia())->verificar('POSLOC1-@@@invalido@@@.@@@tampoco@@@', 'MID-TEST-0001'))
        ->toThrow(LicenciaInvalidaException::class);
});

test('firmar sin llave privada configurada lanza un error claro', function () {
    Config::set('pos.license.private_key', null);

    expect(fn () => (new FirmadorLicencia())->firmar(codigoActivacionDeTest()))
        ->toThrow(LicenciaInvalidaException::class);
});

test('verificar sin llave publica configurada lanza un error claro', function () {
    prepararLlavesLicenciaTest();
    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest());

    Config::set('pos.license.public_key', '');

    expect(fn () => (new ValidadorLicencia())->verificar($codigoFirmado, 'MID-TEST-0001'))
        ->toThrow(LicenciaInvalidaException::class);
});

test('parsear() no exige machine_id -- solo valida forma y firma', function () {
    prepararLlavesLicenciaTest();

    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest('MID-AAAA-0001'));

    $resultado = (new ValidadorLicencia())->parsear($codigoFirmado);

    expect($resultado->machineId)->toBe('MID-AAAA-0001');
});

test('sin especificar rol, el codigo firmado es de rol servidor por defecto', function () {
    prepararLlavesLicenciaTest();

    $codigoFirmado = (new FirmadorLicencia())->firmar(codigoActivacionDeTest());
    $resultado = (new ValidadorLicencia())->verificar($codigoFirmado, 'MID-TEST-0001');

    expect($resultado->rol)->toBe('servidor');
});

test('un codigo firmado explicitamente con rol cliente lo conserva al verificar', function () {
    prepararLlavesLicenciaTest();

    $codigo = new CodigoActivacion(
        licenciaId: (string) Str::uuid(),
        empresaId: 1,
        empresaNombre: 'Empresa de prueba',
        machineId: 'MID-TEST-0001',
        issuedAt: new DateTimeImmutable(),
        rol: 'cliente',
    );
    $codigoFirmado = (new FirmadorLicencia())->firmar($codigo);
    $resultado = (new ValidadorLicencia())->verificar($codigoFirmado, 'MID-TEST-0001');

    expect($resultado->rol)->toBe('cliente');
});

test('un codigo viejo emitido antes de que existiera el campo rol se sigue verificando como servidor', function () {
    prepararLlavesLicenciaTest();

    // Simula un codigo firmado ANTES de que CodigoActivacion tuviera 'rol'
    // en el payload -- construido a mano en vez de con el value object
    // actual, para probar la compatibilidad hacia atras de verdad.
    $payloadSinRol = [
        'v' => 1,
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 1,
        'empresa_nombre' => 'Empresa vieja',
        'machine_id' => 'MID-TEST-0001',
        'issued_at' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
    $payloadJson = json_encode($payloadSinRol, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $privateKey = base64_decode(config('pos.license.private_key'), true);
    $firma = sodium_crypto_sign_detached($payloadJson, $privateKey);

    $codigoFirmado = sprintf(
        'POSLOC%d-%s.%s',
        1,
        \App\Services\LocalLicense\Base64Url::encode($payloadJson),
        \App\Services\LocalLicense\Base64Url::encode($firma)
    );

    $resultado = (new ValidadorLicencia())->verificar($codigoFirmado, 'MID-TEST-0001');

    expect($resultado->rol)->toBe('servidor');
});
