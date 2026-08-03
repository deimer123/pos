<?php

use Illuminate\Support\Facades\Storage;

// Ruta /storage/{path} (routes/web.php): sirve el disco "public" cuando no
// existe el symlink real public/storage -- caso de Turion/Local, donde
// crear un symlink en Windows sin privilegios de administrador no es
// confiable. En el droplet el symlink real ya intercepta estos archivos
// antes de que Laravel los vea, asi que esta ruta no cambia nada ahi.

test('sirve un archivo existente del disco public', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/prueba.jpg', 'contenido-de-prueba');

    $respuesta = $this->get('/storage/logos/prueba.jpg');

    // BinaryFileResponse (lo que devuelve Storage::response()) transmite el
    // archivo directo a la salida y su getContent() siempre da false por
    // diseño de Symfony -- se verifica por Content-Length en vez de por el
    // contenido en si.
    $respuesta->assertOk();
    expect((int) $respuesta->headers->get('Content-Length'))->toBe(strlen('contenido-de-prueba'));
});

test('un archivo que no existe da 404', function () {
    Storage::fake('public');

    $this->get('/storage/logos/no-existe.jpg')->assertNotFound();
});
