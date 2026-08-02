<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

// El script de actualizaciones automaticas (resources/js/turion-updater.js)
// solo tiene sentido para la edicion hibrida/Turion -- la edicion Local
// saco el auto-updater (ver src-tauri-local/src/lib.rs, sin
// tauri-plugin-updater) porque cada actualizacion se cobra aparte y se
// entrega a mano. El render hook en AdminPanelProvider gatea esto con
// PosEdition::esHibrida().

function simularEdicionParaLogin(string $edicion): void
{
    $connection = DB::connection();
    $prop = new ReflectionProperty($connection, 'config');
    $prop->setAccessible(true);
    $config = $prop->getValue($connection);
    $config['driver'] = 'sqlite';
    $prop->setValue($connection, $config);

    Config::set('pos.edition', $edicion);
}

test('el login de una empresa online (droplet) no carga el script de actualizaciones', function () {
    $respuesta = $this->get('/admin/login');

    $respuesta->assertOk();
    $respuesta->assertDontSee('turion-updater', escape: false);
});

test('el login en edicion hibrida si carga el script de actualizaciones', function () {
    simularEdicionParaLogin('hibrida');

    $respuesta = $this->get('/admin/login');

    $respuesta->assertOk();
    $respuesta->assertSee('turion-updater', escape: false);
});

test('el login en edicion Local NO carga el script de actualizaciones', function () {
    simularEdicionParaLogin('local');

    $respuesta = $this->get('/admin/login');

    $respuesta->assertOk();
    $respuesta->assertDontSee('turion-updater', escape: false);
});
