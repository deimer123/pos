<?php

use App\Filament\Resources\SucursalResource;
use App\Filament\Resources\SucursalResource\Pages\CreateSucursal;
use App\Filament\Resources\SucursalResource\Pages\EditSucursal;
use App\Models\ConfiguracionEmpresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Sucursales\ActivarSucursales;
use App\Services\Sucursales\SucursalActual;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 4 del modulo de sucursales: pantalla para administrarlas, asignar
// empleados y elegir con que sucursal se trabaja.

function empresaConSucursalesPaso4(bool $activa = true): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'tipo_edicion' => 'online',
        'usa_sucursales' => $activa,
    ]);
    $empresa->assignRole('admin_empresa');

    if ($activa) {
        Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Principal', 'es_principal' => true, 'activo' => true]);
    }

    return $empresa;
}

function empleadoPaso4(User $empresa): User
{
    return User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
}

// ------------------------------------------------------------------ acceso

test('solo el admin de una empresa con sucursales ve la pantalla', function () {
    $conSucursales = empresaConSucursalesPaso4();
    $sinSucursales = empresaConSucursalesPaso4(false);
    $empleado = empleadoPaso4($conSucursales);

    $this->actingAs($conSucursales);
    expect(SucursalResource::canAccess())->toBeTrue();

    $this->actingAs($sinSucursales);
    expect(SucursalResource::canAccess())->toBeFalse();

    $this->actingAs($empleado);
    expect(SucursalResource::canAccess())->toBeFalse();
});

// ------------------------------------------------- opciones y sucursal actual

test('el admin ve todas las sucursales activas de su empresa y ninguna de otra', function () {
    $empresa = empresaConSucursalesPaso4();
    $otra = empresaConSucursalesPaso4();
    Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Norte', 'activo' => true]);
    Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Cerrada', 'activo' => false]);

    $nombres = app(SucursalActual::class)->opciones($empresa)->pluck('nombre')->all();

    expect($nombres)->toBe(['Principal', 'Norte']);
});

test('un empleado solo ve las sucursales que tiene asignadas', function () {
    $empresa = empresaConSucursalesPaso4();
    $norte = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Norte', 'activo' => true]);
    $sur = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Sur', 'activo' => true]);
    $empleado = empleadoPaso4($empresa);
    $norte->usuarios()->attach($empleado->id);

    $servicio = app(SucursalActual::class);

    expect($servicio->opciones($empleado)->pluck('id')->all())->toBe([$norte->id])
        ->and($servicio->actual($empleado)->id)->toBe($norte->id)
        ->and($servicio->establecer($empleado, $sur->id))->toBeFalse()
        ->and($servicio->establecer($empleado, 'todas'))->toBeFalse();
});

test('el admin trabaja con la Principal por defecto y puede elegir Todas', function () {
    $empresa = empresaConSucursalesPaso4();
    $norte = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Norte', 'activo' => true]);
    $servicio = app(SucursalActual::class);

    expect($servicio->actual($empresa)->nombre)->toBe('Principal');

    expect($servicio->establecer($empresa, $norte->id))->toBeTrue();
    expect($servicio->actual($empresa)->id)->toBe($norte->id);

    expect($servicio->establecer($empresa, 'todas'))->toBeTrue();
    expect($servicio->actual($empresa))->toBeNull();
});

test('una empresa sin sucursales no tiene opciones ni sucursal actual', function () {
    $empresa = empresaConSucursalesPaso4(false);
    $servicio = app(SucursalActual::class);

    expect($servicio->opciones($empresa))->toBeEmpty()
        ->and($servicio->actual($empresa))->toBeNull();
});

test('no se puede cambiar a la sucursal de otra empresa', function () {
    $empresa = empresaConSucursalesPaso4();
    $otra = empresaConSucursalesPaso4();
    $ajena = Sucursal::where('empresa_id', $otra->id)->first();

    $this->actingAs($empresa)
        ->post(route('sucursal.cambiar'), ['sucursal' => $ajena->id])
        ->assertForbidden();
});

test('el selector cambia la sucursal de la sesion', function () {
    $empresa = empresaConSucursalesPaso4();
    $norte = Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Norte', 'activo' => true]);

    $this->actingAs($empresa)
        ->post(route('sucursal.cambiar'), ['sucursal' => $norte->id])
        ->assertRedirect();

    expect(session(SucursalActual::SESSION_KEY))->toBe($norte->id);
});

// ------------------------------------------------------- crear y editar

test('crear una sucursal la deja en la empresa, no principal, con sus empleados', function () {
    $empresa = empresaConSucursalesPaso4();
    $empleado = empleadoPaso4($empresa);

    Livewire::actingAs($empresa)
        ->test(CreateSucursal::class)
        ->fillForm(['nombre' => 'Norte', 'usuarios' => [$empleado->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $norte = Sucursal::where('empresa_id', $empresa->id)->where('nombre', 'Norte')->first();

    expect($norte)->not->toBeNull()
        ->and($norte->es_principal)->toBeFalse()
        ->and($norte->usuarios()->pluck('users.id')->all())->toBe([$empleado->id]);
});

test('no se repite el nombre dentro de la misma empresa', function () {
    $empresa = empresaConSucursalesPaso4();

    Livewire::actingAs($empresa)
        ->test(CreateSucursal::class)
        ->fillForm(['nombre' => 'Principal'])
        ->call('create')
        ->assertHasFormErrors(['nombre' => 'unique']);
});

test('una empresa con mesas o cocina no puede crear una segunda sucursal todavia', function (string $modulo) {
    $empresa = empresaConSucursalesPaso4();
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Restaurante',
        'representante_legal' => 'Rep',
        'tipo_negocio' => 'restaurante',
    ]);
    DB::table('configuracion_empresas')->where('empresa_id', $empresa->id)->update([$modulo => true]);

    Livewire::actingAs($empresa)
        ->test(CreateSucursal::class)
        ->fillForm(['nombre' => 'Norte'])
        ->call('create');

    expect(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(1);
})->with(['usa_mesas', 'usa_cocina']);

test('la Principal no se puede desactivar', function () {
    $empresa = empresaConSucursalesPaso4();
    $principal = Sucursal::where('empresa_id', $empresa->id)->first();

    Livewire::actingAs($empresa)
        ->test(EditSucursal::class, ['record' => $principal->id])
        ->fillForm(['nombre' => 'Casa matriz', 'activo' => false])
        ->call('save');

    $principal->refresh();

    expect($principal->nombre)->toBe('Casa matriz')
        ->and($principal->activo)->toBeTrue()
        ->and($principal->es_principal)->toBeTrue();
});

test('no se puede editar la sucursal de otra empresa', function () {
    $empresa = empresaConSucursalesPaso4();
    $otra = empresaConSucursalesPaso4();
    $ajena = Sucursal::where('empresa_id', $otra->id)->first();

    // El recurso filtra por empresa: la sucursal ajena "no existe" para esta
    // empresa y Filament lanza ModelNotFoundException (dentro de un
    // ViewException).
    expect(fn () => Livewire::actingAs($empresa)
        ->test(EditSucursal::class, ['record' => $ajena->id]))
        ->toThrow(Throwable::class);

    expect($ajena->fresh()->nombre)->toBe('Principal');
});

// -------------------------------------------------------------- mesas

test('al activar, las mesas existentes pasan a la Principal y no se pierde ninguna', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online']);
    $otra = User::factory()->create(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online']);

    foreach ([[$empresa->id, 'M1'], [$empresa->id, 'M2'], [$otra->id, 'M1']] as [$id, $codigo]) {
        DB::table('mesas')->insert([
            'empresa_id' => $id, 'codigo' => $codigo, 'nombre' => "Mesa {$codigo}",
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $resultado = app(ActivarSucursales::class)->activar($empresa);

    expect(DB::table('mesas')->count())->toBe(3)
        ->and(DB::table('mesas')->where('empresa_id', $empresa->id)->where('sucursal_id', $resultado['sucursal_id'])->count())->toBe(2)
        ->and(DB::table('mesas')->where('empresa_id', $otra->id)->whereNull('sucursal_id')->count())->toBe(1);
});
