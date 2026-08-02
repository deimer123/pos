<?php

use App\Filament\Pages\ConectarTerminalesLocal;
use App\Models\ActivacionLocal;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

// Pagina Filament (edicion Local) donde el dueno del negocio ve la
// direccion de red de su servidor y los terminales ya conectados -- ver
// App\Filament\Pages\ConectarTerminalesLocal.

function simularEdicionLocalConectarTerminales(): void
{
    $connection = DB::connection();
    $prop = new ReflectionProperty($connection, 'config');
    $prop->setAccessible(true);
    $config = $prop->getValue($connection);
    $config['driver'] = 'sqlite';
    $prop->setValue($connection, $config);

    Config::set('pos.edition', 'local');
}

function prepararTablaActivacionLocalConectarTerminales(): void
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

afterEach(function () {
    Schema::dropIfExists('activacion_local');
});

test('canAccess() es solo para la edicion Local', function () {
    expect(ConectarTerminalesLocal::canAccess())->toBeFalse();

    simularEdicionLocalConectarTerminales();

    expect(ConectarTerminalesLocal::canAccess())->toBeTrue();
});

test('sin lan_address configurado, urlParaTerminales() devuelve null', function () {
    simularEdicionLocalConectarTerminales();
    prepararTablaActivacionLocalConectarTerminales();
    Config::set('pos.lan_address', null);

    Livewire::test(ConectarTerminalesLocal::class)
        ->assertSee('No se pudo detectar la dirección de red');
});

test('con lan_address configurado, muestra la url de emparejar-terminal', function () {
    simularEdicionLocalConectarTerminales();
    prepararTablaActivacionLocalConectarTerminales();
    Config::set('pos.lan_address', '192.168.1.5:8734');

    Livewire::test(ConectarTerminalesLocal::class)
        ->assertSee('http://192.168.1.5:8734/emparejar-terminal');
});

test('lista los terminales cliente conectados, sin incluir la fila del servidor', function () {
    simularEdicionLocalConectarTerminales();
    prepararTablaActivacionLocalConectarTerminales();

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 1,
        'empresa_nombre' => 'Empresa',
        'machine_id' => 'MID-SERVIDOR-0001',
        'rol' => 'servidor',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 1,
        'empresa_nombre' => 'Empresa',
        'machine_id' => 'MID-TERMINAL-0002',
        'rol' => 'cliente',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    $component = Livewire::test(ConectarTerminalesLocal::class);

    $component->assertSee('MID-TERMINAL-0002');
    $component->assertDontSee('MID-SERVIDOR-0001');
});

test('revocarTerminal() elimina la fila del terminal', function () {
    simularEdicionLocalConectarTerminales();
    prepararTablaActivacionLocalConectarTerminales();

    $terminal = ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 1,
        'empresa_nombre' => 'Empresa',
        'machine_id' => 'MID-TERMINAL-0002',
        'rol' => 'cliente',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    Livewire::test(ConectarTerminalesLocal::class)
        ->call('revocarTerminal', $terminal->id);

    expect(ActivacionLocal::find($terminal->id))->toBeNull();
});
