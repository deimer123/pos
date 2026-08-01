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

/*
|--------------------------------------------------------------------------
| Limpieza manual tras commit implicito (Schema::create/dropIfExists/
| Schema::table()->addColumn/dropColumn)
|--------------------------------------------------------------------------
|
| Varios tests de Turion/LocalLicense corren DDL a mano contra la BD de
| pruebas (mysql) para simular tablas/columnas exclusivas de sqlite. En
| MySQL/InnoDB, cualquier DDL hace COMMIT IMPLICITO de la transaccion que
| RefreshDatabase envuelve alrededor de cada test -- asi que todo lo que el
| test cree DESPUES de ese DDL sobrevive permanentemente en vez de
| revertirse solo. marcarAltaLimpiezaManual()/limpiarTrasCommitImplicito()
| son el equivalente reutilizable del afterEach() manual que ya tenia
| ActivacionLocalTest.php, para los tests que crean una empresa (User
| tipo_usuario=empresa) y sus datos asociados.
|
| Solo hace falta encadenar 'users' donde el modelo lo permite: casi todo lo
| que UserObserver::created() siembra (actors, products, familias,
| subfamilias, configuracion_empresas, taller_ordenes, hotel_*, prefacturas)
| tiene FK con cascadeOnDelete() hacia users, asi que borrar el/los User de
| id > la marca los arrastra a todos. Las dos excepciones son
| cuentas_contables (empresa_id sin FK/cascade) y model_has_roles (tabla
| polimorfica de Spatie sin FK hacia users), que se limpian aparte.
*/
function marcarAltaLimpiezaManual(): int
{
    return (int) (DB::table('users')->max('id') ?? 0);
}

function limpiarTrasCommitImplicito(int $marcaUserId): void
{
    DB::table('model_has_roles')
        ->where('model_id', '>', $marcaUserId)
        ->where('model_type', \App\Models\User::class)
        ->delete();

    DB::table('cuentas_contables')->where('empresa_id', '>', $marcaUserId)->delete();

    DB::table('users')->where('id', '>', $marcaUserId)->delete();
}
