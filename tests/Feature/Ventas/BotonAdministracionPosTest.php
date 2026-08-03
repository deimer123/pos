<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

// El boton "Administracion" en el topbar del POS (resources/views/layouts/
// pos.blade.php) usaba `DB::getDriverName() === 'sqlite'` para ocultarse
// -- eso es cierto tanto para Turion (hibrida, que SI bloquea /admin via
// BloquearAdminEnTurion, donde el boton es correcto ocultarlo) como para
// la edicion Local (que NO bloquea /admin, ahi el boton debia mostrarse).
// Ahora usa PosEdition::esHibrida() para distinguir los dos casos.

function simularEdicionParaPos(string $edicion): void
{
    $connection = DB::connection();
    $prop = new ReflectionProperty($connection, 'config');
    $prop->setAccessible(true);
    $config = $prop->getValue($connection);
    $config['driver'] = 'sqlite';
    $prop->setValue($connection, $config);

    Config::set('pos.edition', $edicion);
}

// El topbar del POS renderiza @livewire('turion-sync-bar') siempre -- ese
// componente solo consulta pending_sync_operations/sync_state cuando
// PosEdition::esHibrida() es true (ver App\Livewire\TurionSyncBar::mount()),
// asi que unicamente el caso 'hibrida' de este test necesita esas tablas
// (no existen en la BD de pruebas, que es mysql). sync_state necesita al
// menos una fila para que EnsureTerminalEmparejada no redirija a
// /emparejar por "terminal sin emparejar".
function prepararTablasTurionParaPos(): void
{
    Schema::dropIfExists('pending_sync_operations');
    Schema::create('pending_sync_operations', function ($table) {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('tipo');
        $table->json('payload');
        $table->string('estado')->default('pendiente');
        $table->text('error')->nullable();
        $table->unsignedBigInteger('resultado_id')->nullable();
        $table->timestamp('sincronizado_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('sync_state');
    Schema::create('sync_state', function ($table) {
        $table->id();
        $table->unsignedBigInteger('empresa_id')->nullable();
        $table->string('empresa_nombre')->nullable();
        $table->text('terminal_token')->nullable();
        $table->string('servidor_url')->nullable();
        $table->timestamp('ultima_sincronizacion_at')->nullable();
        $table->timestamp('ultima_subida_at')->nullable();
        $table->timestamps();
    });

    DB::table('sync_state')->insert(['empresa_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

afterEach(function () {
    // Schema::create()/dropIfExists() (DDL) hacen commit implicito en
    // MySQL/InnoDB -- el usuario/ConfiguracionEmpresa que crea el test de
    // hibrida (el unico que llama prepararTablasTurionParaPos()) sobrevive
    // al rollback automatico de RefreshDatabase, se limpia a mano.
    Schema::dropIfExists('pending_sync_operations');
    Schema::dropIfExists('sync_state');

    $usuario = User::where('email', 'admin-empresa-hibrida-pos@example.test')->first();

    if ($usuario) {
        DB::table('model_has_roles')->where('model_id', $usuario->id)->where('model_type', User::class)->delete();
        ConfiguracionEmpresa::where('empresa_id', $usuario->id)->delete();
        $usuario->delete();
    }
});

function crearAdminEmpresaParaPos(?string $email = null): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_filter([
        'tipo_usuario' => 'empresa',
        'email' => $email,
    ]));
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

test('online (droplet) muestra el boton de Administracion en el POS', function () {
    $empresa = crearAdminEmpresaParaPos();

    $this->actingAs($empresa)->get('/pos')->assertOk()->assertSee('Administracion');
});

test('edicion Local muestra el boton de Administracion en el POS', function () {
    $empresa = crearAdminEmpresaParaPos();
    simularEdicionParaPos('local');

    $this->actingAs($empresa)->get('/pos')->assertOk()->assertSee('Administracion');
});

test('edicion hibrida (Turion) NO muestra el boton de Administracion en el POS', function () {
    $empresa = crearAdminEmpresaParaPos('admin-empresa-hibrida-pos@example.test');
    simularEdicionParaPos('hibrida');
    prepararTablasTurionParaPos();

    $this->actingAs($empresa)->get('/pos')->assertOk()->assertDontSee('Administracion');
});
