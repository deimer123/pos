<?php

use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Models\ConfiguracionEmpresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Sucursales\ActivarSucursales;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Paso 3 del modulo de sucursales: el super_admin enciende el interruptor y
// el servicio crea la Principal, le asigna lo que la empresa ya tenia y
// verifica que no se perdio ni se movio nada. Las tablas kardex y cajas se
// usan como representantes: tienen pocas columnas obligatorias y cubren los
// dos caminos (empresa_id directo y empresa_id NULL deducido por usuario).

function empresaOnlineSucursalesTest(array $extra = []): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online'], $extra));
    $empresa->assignRole('admin_empresa');

    return $empresa;
}

function superAdminSucursalesTest(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

function kardexSucursalesTest(int $empresaId, int $cantidad = 1): void
{
    for ($i = 0; $i < $cantidad; $i++) {
        DB::table('kardex')->insert([
            'empresa_id' => $empresaId,
            'producto_id' => 'P' . $i,
            'tipo' => 'compra',
            'entrada' => 1,
            'salida' => 0,
            'stock_anterior' => 0,
            'stock_actual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function cajaSucursalesTest(int $userId, ?int $empresaId): void
{
    DB::table('cajas')->insert([
        'user_id' => $userId,
        'empresa_id' => $empresaId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ---------------------------------------------------------------- servicio

test('activar crea la sucursal Principal y asigna lo que la empresa ya tenia sin cambiar los conteos', function () {
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 3);
    cajaSucursalesTest($empresa->id, $empresa->id);

    $resultado = app(ActivarSucursales::class)->activar($empresa);

    $principal = Sucursal::where('empresa_id', $empresa->id)->firstOrFail();

    expect($principal->nombre)->toBe('Principal')
        ->and($principal->es_principal)->toBeTrue()
        ->and($empresa->fresh()->usa_sucursales)->toBeTrue()
        ->and($resultado['asignadas']['kardex'])->toBe(3)
        ->and($resultado['asignadas']['cajas'])->toBe(1);

    // Misma cantidad de registros que antes, todos en la Principal.
    expect(DB::table('kardex')->where('empresa_id', $empresa->id)->count())->toBe(3)
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->where('sucursal_id', $principal->id)->count())->toBe(3)
        ->and(DB::table('cajas')->where('empresa_id', $empresa->id)->where('sucursal_id', $principal->id)->count())->toBe(1);
});

test('activar no toca los datos de otra empresa', function () {
    $empresa = empresaOnlineSucursalesTest();
    $otra = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 2);
    kardexSucursalesTest($otra->id, 4);
    cajaSucursalesTest($otra->id, $otra->id);

    app(ActivarSucursales::class)->activar($empresa);

    expect(DB::table('kardex')->where('empresa_id', $otra->id)->whereNull('sucursal_id')->count())->toBe(4)
        ->and(DB::table('cajas')->where('empresa_id', $otra->id)->whereNull('sucursal_id')->count())->toBe(1)
        ->and(Sucursal::where('empresa_id', $otra->id)->count())->toBe(0)
        ->and($otra->fresh()->usa_sucursales)->toBeFalse();
});

test('una caja sin empresa_id se asigna por el usuario de la empresa y no por otra', function () {
    $empresa = empresaOnlineSucursalesTest();
    $otra = empresaOnlineSucursalesTest();
    $cajero = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $cajeroOtra = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $otra->id]);

    cajaSucursalesTest($cajero->id, null);
    cajaSucursalesTest($cajeroOtra->id, null);

    app(ActivarSucursales::class)->activar($empresa);

    $principal = Sucursal::where('empresa_id', $empresa->id)->firstOrFail();

    expect(DB::table('cajas')->where('user_id', $cajero->id)->value('sucursal_id'))->toEqual($principal->id)
        ->and(DB::table('cajas')->where('user_id', $cajeroOtra->id)->value('sucursal_id'))->toBeNull();
});

test('activar asigna los empleados existentes a la Principal', function () {
    $empresa = empresaOnlineSucursalesTest();
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $empresa->id]);
    $otroEmpleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => empresaOnlineSucursalesTest()->id]);

    $resultado = app(ActivarSucursales::class)->activar($empresa);

    $principal = Sucursal::where('empresa_id', $empresa->id)->firstOrFail();

    expect($resultado['empleados'])->toBe(1)
        ->and($principal->usuarios->pluck('id')->all())->toEqual([$empleado->id])
        ->and($otroEmpleado->sucursalesAsignadas()->count())->toBe(0);
});

test('activar es idempotente: no duplica la Principal y solo asigna lo que falte', function () {
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 2);

    app(ActivarSucursales::class)->activar($empresa);
    kardexSucursalesTest($empresa->id, 1); // llega un registro sin sucursal despues

    $resultado = app(ActivarSucursales::class)->activar($empresa->fresh());

    expect(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(1)
        ->and($resultado['asignadas']['kardex'])->toBe(1)
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(0);
});

test('activar no cambia updated_at de los registros que asigna', function () {
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 1);
    DB::table('kardex')->where('empresa_id', $empresa->id)->update(['updated_at' => '2026-01-15 10:00:00']);

    app(ActivarSucursales::class)->activar($empresa);

    expect(DB::table('kardex')->where('empresa_id', $empresa->id)->value('updated_at'))->toBe('2026-01-15 10:00:00');
});

test('no se activa en ediciones que no son online y no cambia nada', function (string $edicion) {
    $empresa = empresaOnlineSucursalesTest(['tipo_edicion' => $edicion]);
    kardexSucursalesTest($empresa->id, 2);

    expect(fn () => app(ActivarSucursales::class)->activar($empresa))
        ->toThrow(RuntimeException::class);

    expect(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(0)
        ->and($empresa->fresh()->usa_sucursales)->toBeFalse()
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(2);
})->with(['hibrida', 'local']);

test('no se activa en empresas con modulos incompatibles y no cambia nada', function (string $modulo) {
    $empresa = empresaOnlineSucursalesTest();
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);
    DB::table('configuracion_empresas')->where('empresa_id', $empresa->id)->update([$modulo => true]);
    kardexSucursalesTest($empresa->id, 2);

    expect(fn () => app(ActivarSucursales::class)->activar($empresa))
        ->toThrow(RuntimeException::class);

    expect(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(0)
        ->and($empresa->fresh()->usa_sucursales)->toBeFalse()
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(2);
})->with(['usa_lotes', 'usa_variantes', 'usa_mesas', 'usa_taller', 'usa_hotel', 'usa_servicio_tecnico']);

// ----------------------------------------------------------------- comando

test('--dry-run no cambia nada', function () {
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 2);

    $this->artisan('sucursales:activar', ['empresa' => $empresa->id, '--dry-run' => true])
        ->assertSuccessful();

    expect(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(0)
        ->and($empresa->fresh()->usa_sucursales)->toBeFalse()
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(2);
});

test('el comando con --force activa y asigna', function () {
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 2);

    $this->artisan('sucursales:activar', ['empresa' => $empresa->id, '--force' => true])
        ->assertSuccessful();

    expect($empresa->fresh()->usa_sucursales)->toBeTrue()
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(0);
});

test('el comando falla con un ID que no es una empresa', function () {
    $this->artisan('sucursales:activar', ['empresa' => 999999, '--force' => true])
        ->assertFailed();
});

// -------------------------------------------------------------- formulario

test('el interruptor de sucursales solo se ve en edicion online', function () {
    $superAdmin = superAdminSucursalesTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->assertFormFieldIsVisible('usa_sucursales')
        ->set('data.tipo_edicion', 'hibrida')
        ->assertFormFieldIsHidden('usa_sucursales')
        ->set('data.tipo_edicion', 'online')
        ->assertFormFieldIsVisible('usa_sucursales');
});

test('crear una empresa con el interruptor encendido crea la Principal', function () {
    $superAdmin = superAdminSucursalesTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Repuestos Camacho',
            'email' => 'camacho@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'usa_sucursales' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'camacho@example.com')->firstOrFail();

    expect($empresa->usa_sucursales)->toBeTrue()
        ->and($empresa->sucursales()->count())->toBe(1)
        ->and($empresa->sucursales()->first()->es_principal)->toBeTrue();
});

test('crear una empresa sin tocar el interruptor no activa sucursales', function () {
    $superAdmin = superAdminSucursalesTest();

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Tienda Simple',
            'email' => 'simple@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'simple@example.com')->firstOrFail();

    expect($empresa->usa_sucursales)->toBeFalse()
        ->and($empresa->sucursales()->count())->toBe(0);
});

test('editar una empresa y encender el interruptor activa y asigna sus datos', function () {
    $superAdmin = superAdminSucursalesTest();
    $empresa = empresaOnlineSucursalesTest();
    kardexSucursalesTest($empresa->id, 2);

    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->fillForm(['usa_sucursales' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $empresa->refresh();

    expect($empresa->usa_sucursales)->toBeTrue()
        ->and($empresa->sucursales()->count())->toBe(1)
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(0);
});

test('editar una empresa con modulos incompatibles no deja activar sucursales', function () {
    $superAdmin = superAdminSucursalesTest();
    $empresa = empresaOnlineSucursalesTest();
    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);
    DB::table('configuracion_empresas')->where('empresa_id', $empresa->id)->update(['usa_lotes' => true]);
    kardexSucursalesTest($empresa->id, 2);

    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->fillForm(['usa_sucursales' => true])
        ->call('save');

    expect($empresa->fresh()->usa_sucursales)->toBeFalse()
        ->and(Sucursal::where('empresa_id', $empresa->id)->count())->toBe(0)
        ->and(DB::table('kardex')->where('empresa_id', $empresa->id)->whereNull('sucursal_id')->count())->toBe(2);
});

test('con mas de una sucursal el interruptor queda bloqueado', function () {
    $superAdmin = superAdminSucursalesTest();
    $empresa = empresaOnlineSucursalesTest();

    app(ActivarSucursales::class)->activar($empresa);

    // Solo la Principal: todavia se puede apagar.
    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->assertFormFieldIsEnabled('usa_sucursales');

    Sucursal::create(['empresa_id' => $empresa->id, 'nombre' => 'Arauquita']);

    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->assertFormFieldIsDisabled('usa_sucursales');
});

test('una empresa con sucursales no puede pasarse a otra edicion', function () {
    $superAdmin = superAdminSucursalesTest();
    $empresa = empresaOnlineSucursalesTest();

    app(ActivarSucursales::class)->activar($empresa);

    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->fillForm(['tipo_edicion' => 'hibrida'])
        ->call('save');

    expect($empresa->fresh()->tipo_edicion)->toBe('online');
});
