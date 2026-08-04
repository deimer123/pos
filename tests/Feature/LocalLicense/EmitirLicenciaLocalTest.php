<?php

use App\Filament\Pages\EmitirLicenciaLocal;
use App\Models\LicenciaLocal;
use App\Models\User;
use App\Services\LocalLicense\ValidadorLicencia;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Pagina Filament (solo super_admin) para emitir codigos de activacion
// offline de la edicion Local -- ver app/Filament/Pages/EmitirLicenciaLocal.php.

function crearSuperAdminLicenciaTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

function prepararLlavesLicenciaFilamentTest(): void
{
    $keypair = sodium_crypto_sign_keypair();

    Config::set('pos.license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
    Config::set('pos.license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
}

test('canAccess() es solo para super_admin', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');
    $this->actingAs($empresa);

    expect(EmitirLicenciaLocal::canAccess())->toBeFalse();
});

test('super_admin puede emitir un codigo de activacion valido para una empresa', function () {
    $superAdmin = crearSuperAdminLicenciaTest();
    prepararLlavesLicenciaFilamentTest();

    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'name' => 'Restaurante El Fogon']);

    Livewire::actingAs($superAdmin)
        ->test(EmitirLicenciaLocal::class)
        ->fillForm([
            'empresa_id' => $empresaCliente->id,
            'machine_id' => 'MID-TEST-0001',
            'machine_label' => 'Caja principal',
        ])
        ->call('emitirCodigo')
        ->assertHasNoFormErrors();

    $licencia = LicenciaLocal::where('empresa_id', $empresaCliente->id)->first();

    expect($licencia)->not->toBeNull();
    expect($licencia->machine_id)->toBe('MID-TEST-0001');
    expect($licencia->machine_label)->toBe('Caja principal');
    expect($licencia->creado_por)->toBe($superAdmin->id);

    $verificado = (new ValidadorLicencia())->verificar($licencia->codigo_emitido, 'MID-TEST-0001');
    expect($verificado->empresaId)->toBe($empresaCliente->id);
    expect($verificado->empresaNombre)->toBe('Restaurante El Fogon');
});

test('no permite emitir dos licencias para la misma empresa y la misma maquina', function () {
    $superAdmin = crearSuperAdminLicenciaTest();
    prepararLlavesLicenciaFilamentTest();

    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa']);

    LicenciaLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => $empresaCliente->id,
        'machine_id' => 'MID-DUP-0001',
        'creado_por' => $superAdmin->id,
        'codigo_emitido' => 'POSLOC1-dummy.dummy',
        'issued_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(EmitirLicenciaLocal::class)
        ->fillForm([
            'empresa_id' => $empresaCliente->id,
            'machine_id' => 'MID-DUP-0001',
        ])
        ->call('emitirCodigo');

    expect(
        LicenciaLocal::where('empresa_id', $empresaCliente->id)->where('machine_id', 'MID-DUP-0001')->count()
    )->toBe(1);
});

test('el historial de licencias trae el codigo emitido para poder copiarlo de nuevo', function () {
    $superAdmin = crearSuperAdminLicenciaTest();

    LicenciaLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => User::factory()->create(['tipo_usuario' => 'empresa'])->id,
        'machine_id' => 'MID-HIST-0001',
        'creado_por' => $superAdmin->id,
        'codigo_emitido' => 'POSLOC1-codigoDeHistorial.firmaDeHistorial',
        'issued_at' => now(),
    ]);

    // La notificacion de "ya existe una licencia" le dice al super_admin que
    // puede copiarla de nuevo desde este historial -- el codigo tiene que
    // estar realmente disponible ahi (no solo el resto de las columnas),
    // sin importar donde termine expuesto en el HTML.
    Livewire::actingAs($superAdmin)
        ->test(EmitirLicenciaLocal::class)
        ->assertSee('POSLOC1-codigoDeHistorial.firmaDeHistorial', false);
});
