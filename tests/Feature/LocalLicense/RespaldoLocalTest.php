<?php

use App\Filament\Pages\RespaldoLocal;
use App\Models\ActivacionLocal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Backup/restore de la edicion Local (ver app/Filament/Pages/RespaldoLocal.php).
// VACUUM INTO es sintaxis real de SQLite -- a diferencia de otros tests de
// Turion que solo necesitan simular el NOMBRE del driver, este test
// necesita una conexion SQLite de verdad funcionando para poder generar
// un zip real y verificar su contenido.

function conConexionSqliteDeTest(Closure $callback)
{
    $original = config('database.default');
    $tmpDb = storage_path('app/test-local-backup-'.uniqid().'.sqlite');
    File::ensureDirectoryExists(dirname($tmpDb));
    touch($tmpDb);

    config([
        'database.connections.local_backup_test' => [
            'driver' => 'sqlite',
            'database' => $tmpDb,
            'prefix' => '',
        ],
        'database.default' => 'local_backup_test',
    ]);
    DB::purge('local_backup_test');

    // ActivacionLocal::first() (llamado dentro de descargarRespaldo() para
    // armar el manifest.json) necesita que la tabla exista, igual que en
    // cualquier instalacion Local real ya migrada.
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

    try {
        return $callback($tmpDb);
    } finally {
        DB::purge('local_backup_test');
        config(['database.default' => $original]);
        @unlink($tmpDb);
    }
}

function prepararTablaActivacionLocalRespaldo(): void
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

afterEach(function () {
    // Igual que en ActivacionLocalTest.php: Schema::create()/dropIfExists()
    // hacen commit implicito en MySQL e invalidan el rollback automatico
    // de RefreshDatabase, asi que se limpia a mano.
    Schema::dropIfExists('activacion_local');
    File::deleteDirectory(storage_path('app/pending-restore-uploads'));
    File::delete(storage_path('app/pending-restore.json'));
    File::deleteDirectory(storage_path('app/pending-restore'));
});

test('canAccess() es solo para la edicion Local', function () {
    expect(RespaldoLocal::canAccess())->toBeFalse();
});

test('descargarRespaldo() genera un zip con database.sqlite y manifest.json', function () {
    conConexionSqliteDeTest(function () {
        $respuesta = (new RespaldoLocal())->descargarRespaldo();

        expect($respuesta)->toBeInstanceOf(BinaryFileResponse::class);

        $rutaZip = $respuesta->getFile()->getPathname();

        $zip = new ZipArchive();
        expect($zip->open($rutaZip))->toBeTrue();
        expect($zip->locateName('database.sqlite'))->not->toBeFalse();
        expect($zip->locateName('manifest.json'))->not->toBeFalse();

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        expect($manifest)->toHaveKey('generado_at');
        expect($manifest)->toHaveKey('app', 'Sistema POS Local');

        $zip->close();
        @unlink($rutaZip);
    });
});

function crearZipDeRespaldoDeTest(?int $empresaId, string $empresaNombre = 'Empresa Test'): string
{
    $ruta = storage_path('app/pending-restore-uploads/'.Str::uuid().'.zip');
    File::ensureDirectoryExists(dirname($ruta));

    $zip = new ZipArchive();
    $zip->open($ruta, ZipArchive::CREATE);
    $zip->addFromString('database.sqlite', 'contenido-de-prueba');
    $zip->addFromString('manifest.json', json_encode([
        'app' => 'Sistema POS Local',
        'empresa_id' => $empresaId,
        'empresa_nombre' => $empresaNombre,
        'generado_at' => now()->toIso8601String(),
    ]));
    $zip->close();

    return 'pending-restore-uploads/'.basename($ruta);
}

test('prepararRestauracion() acepta un respaldo de la misma empresa y deja el marcador listo', function () {
    prepararTablaActivacionLocalRespaldo();

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 42,
        'empresa_nombre' => 'Ferreteria La Llave',
        'machine_id' => 'MID-TEST-0001',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    $rutaRelativa = crearZipDeRespaldoDeTest(42);

    $page = new RespaldoLocal();
    $page->data = ['archivo_respaldo' => $rutaRelativa];
    $page->prepararRestauracion();

    expect(File::exists(storage_path('app/pending-restore.json')))->toBeTrue();
    expect(File::exists(storage_path('app/pending-restore/database.sqlite')))->toBeTrue();

    File::delete(storage_path('app/pending-restore.json'));
    File::deleteDirectory(storage_path('app/pending-restore'));
});

test('prepararRestauracion() rechaza un respaldo de otra empresa', function () {
    prepararTablaActivacionLocalRespaldo();

    ActivacionLocal::create([
        'licencia_id' => (string) Str::uuid(),
        'empresa_id' => 42,
        'empresa_nombre' => 'Ferreteria La Llave',
        'machine_id' => 'MID-TEST-0001',
        'codigo_raw' => 'x',
        'activada_at' => now(),
    ]);

    $rutaRelativa = crearZipDeRespaldoDeTest(99, 'Otra Empresa');

    $page = new RespaldoLocal();
    $page->data = ['archivo_respaldo' => $rutaRelativa];
    $page->prepararRestauracion();

    expect(File::exists(storage_path('app/pending-restore.json')))->toBeFalse();
});

test('prepararRestauracion() rechaza un archivo que no es un zip valido', function () {
    prepararTablaActivacionLocalRespaldo();

    $ruta = storage_path('app/pending-restore-uploads/'.Str::uuid().'.zip');
    File::ensureDirectoryExists(dirname($ruta));
    File::put($ruta, 'esto no es un zip');

    $page = new RespaldoLocal();
    $page->data = ['archivo_respaldo' => 'pending-restore-uploads/'.basename($ruta)];
    $page->prepararRestauracion();

    expect(File::exists(storage_path('app/pending-restore.json')))->toBeFalse();
});
