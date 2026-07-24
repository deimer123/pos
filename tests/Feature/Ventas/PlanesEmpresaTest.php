<?php

use App\Filament\Resources\ComplementoResource;
use App\Filament\Resources\EmpresaResource\Pages\CreateEmpresa;
use App\Filament\Resources\EmpresaResource\Pages\EditEmpresa;
use App\Filament\Resources\PaqueteUsuariosResource;
use App\Filament\Resources\PlanResource;
use App\Models\Complemento;
use App\Models\PaqueteUsuarios;
use App\Models\Plan;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Sistema de planes/tarifas: solo el super_admin (quien crea las empresas)
// puede ver/administrar Planes, Complementos y Paquetes de usuarios, y
// elegirlos al crear/editar una empresa para que el sistema totalice
// cuanto cobrarle.

function crearSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $user = User::factory()->create(['tipo_usuario' => 'empresa']);
    $user->assignRole('super_admin');

    return $user;
}

test('un admin_empresa no puede ver los recursos de Planes, Complementos ni Paquetes de usuarios', function () {
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    $this->actingAs($empresa);

    expect(PlanResource::canViewAny())->toBeFalse();
    expect(ComplementoResource::canViewAny())->toBeFalse();
    expect(PaqueteUsuariosResource::canViewAny())->toBeFalse();
});

test('un super_admin si puede ver los recursos de Planes, Complementos y Paquetes de usuarios', function () {
    $superAdmin = crearSuperAdmin();
    $this->actingAs($superAdmin);

    expect(PlanResource::canViewAny())->toBeTrue();
    expect(ComplementoResource::canViewAny())->toBeTrue();
    expect(PaqueteUsuariosResource::canViewAny())->toBeTrue();
});

test('crear una empresa con un plan, un complemento y un paquete de usuarios totaliza y guarda todo correctamente', function () {
    $superAdmin = crearSuperAdmin();

    $plan = Plan::create(['nombre' => 'Plan de prueba', 'meses' => 6, 'precio' => 600000, 'usuarios_incluidos' => 2, 'activo' => true]);
    $complemento = Complemento::create(['nombre' => 'Complemento de prueba', 'precio' => 800000, 'activo' => true]);
    $paquete = PaqueteUsuarios::create(['nombre' => 'Paquete de prueba', 'usuarios_adicionales' => 3, 'precio' => 400000, 'activo' => true]);

    Livewire::actingAs($superAdmin)
        ->test(CreateEmpresa::class)
        ->fillForm([
            'name' => 'Empresa con plan',
            'email' => 'empresaconplan@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'plan_id' => $plan->id,
            'complementos_ids' => [$complemento->id],
            'paquete_usuarios_id' => $paquete->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = User::where('email', 'empresaconplan@example.com')->first();

    expect($empresa)->not->toBeNull();
    expect($empresa->plan_id)->toBe($plan->id);
    expect($empresa->paquete_usuarios_id)->toBe($paquete->id);
    // Total = 600.000 (plan) + 800.000 (complemento) + 400.000 (paquete) = 1.800.000
    expect((float) $empresa->valor_plan_total)->toBe(1800000.0);
    // usuarios_incluidos del plan (2) + usuarios_adicionales del paquete (3) = 5
    expect((int) $empresa->max_vendedores)->toBe(5);
    // El plan_meses se autocompleta con la duracion del plan elegido (6).
    expect((int) $empresa->plan_meses)->toBe(6);

    $complementosGuardados = $empresa->complementos()->get();
    expect($complementosGuardados)->toHaveCount(1);
    expect((float) $complementosGuardados->first()->pivot->precio_aplicado)->toBe(800000.0);
});

test('editar una empresa para agregarle un complemento recalcula el total y sincroniza el pivote', function () {
    $superAdmin = crearSuperAdmin();

    $plan = Plan::create(['nombre' => 'Plan base', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $complementoA = Complemento::create(['nombre' => 'Complemento A', 'precio' => 100000, 'activo' => true]);
    $complementoB = Complemento::create(['nombre' => 'Complemento B', 'precio' => 200000, 'activo' => true]);

    $empresa = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'plan_id' => $plan->id,
        'valor_plan_total' => 330000,
    ]);
    $empresa->assignRole('admin_empresa');
    $empresa->complementos()->attach($complementoA->id, ['precio_aplicado' => 100000]);

    Livewire::actingAs($superAdmin)
        ->test(EditEmpresa::class, ['record' => $empresa->getRouteKey()])
        ->assertFormSet(['complementos_ids' => [$complementoA->id]])
        ->fillForm([
            'complementos_ids' => [$complementoA->id, $complementoB->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $empresa->refresh();

    // Total = 330.000 (plan) + 100.000 + 200.000 (complementos) = 630.000
    expect((float) $empresa->valor_plan_total)->toBe(630000.0);
    expect($empresa->complementos()->count())->toBe(2);
});
