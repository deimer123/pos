<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// Tablas de referencia minimas que UserObserver necesita al crear
// cualquier empresa (User con tipo_usuario='empresa'): crea de una vez un
// cliente "CONSUMIDOR FINAL" (Actor) con tipo_documento_id=6,
// departamento_id=1 y ciudad_id=1. Sin esto, cualquier test de Feature que
// cree una empresa falla con un error de llave foranea en una base de
// datos de pruebas recien migrada (RefreshDatabase no siembra datos por su
// cuenta). insertOrIgnore porque RefreshDatabase envuelve cada test en una
// transaccion que se revierte al terminar -- hay que reinsertar antes de
// cada test.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        DB::table('tipos_documento')->insertOrIgnore(['id' => 6, 'nombre' => 'NIT']);
        DB::table('departamentos')->insertOrIgnore(['id' => 1, 'nombre' => 'Santander', 'codigo_dian' => 68]);
        DB::table('ciudades')->insertOrIgnore(['id' => 1, 'nombre' => 'Bucaramanga', 'codigo_dian' => 68001, 'departamento_id' => 1]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
