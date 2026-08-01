<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

test('pos:licencia:generar-llaves imprime un par de llaves nuevo', function () {
    $this->artisan('pos:licencia:generar-llaves')
        ->expectsOutputToContain('POS_LOCAL_LICENSE_PRIVATE_KEY=')
        ->expectsOutputToContain('POS_LOCAL_LICENSE_PUBLIC_KEY=')
        ->assertExitCode(0);
});

test('pos:licencia:emitir firma un codigo para una empresa existente', function () {
    $keypair = sodium_crypto_sign_keypair();
    Config::set('pos.license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
    Config::set('pos.license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'name' => 'Empresa Test']);

    $this->artisan('pos:licencia:emitir', ['empresa_id' => $empresa->id, 'machine_id' => 'MID-TEST-0001'])
        ->expectsOutputToContain('Codigo de activacion generado:')
        ->assertExitCode(0);
});

test('pos:licencia:emitir falla con un mensaje claro si la empresa no existe', function () {
    $this->artisan('pos:licencia:emitir', ['empresa_id' => 999999, 'machine_id' => 'MID-TEST-0001'])
        ->expectsOutputToContain('No existe una empresa')
        ->assertExitCode(1);
});
