<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ventas\FacturarPlanService;
use Spatie\Permission\Models\Role;

// Cobro de plan: el super_admin le genera a una empresa una factura por
// lo que le corresponde pagar segun su plan, a nombre de la "empresa
// emisora" (la del super_admin), reusando la misma tabla facturas/
// factura_detalles que usa cualquier empresa para sus propias ventas.

test('facturar sin empresa emisora configurada lanza un error claro', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $plan = Plan::create(['nombre' => 'Plan de prueba', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'plan_id' => $plan->id, 'valor_plan_total' => 330000]);

    expect(fn () => app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo'))
        ->toThrow(RuntimeException::class, 'No hay ninguna empresa marcada como "Empresa emisora"');
});

test('facturar el plan como salida de mercancia crea la factura a nombre de la empresa emisora', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $emisora = User::factory()->create(['tipo_usuario' => 'empresa', 'name' => 'Mi Empresa SAS', 'es_empresa_emisora' => true]);

    $plan = Plan::create(['nombre' => 'Plan Emprende', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'name' => 'Cliente Tienda X',
        'plan_id' => $plan->id,
        'plan_meses' => 3,
        'valor_plan_total' => 330000,
    ]);

    $factura = app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo');

    expect($factura->empresa_id)->toBe($emisora->id);
    expect((float) $factura->total)->toBe(330000.0);
    expect((float) $factura->saldo)->toBe(0.0);
    expect($factura->estado_pago)->toBe('pagada');
    expect($factura->tipo_factura)->toBe('salida');
    expect($factura->detalles()->count())->toBe(1);
    expect($factura->detalles()->first()->descripcion_larga)->toContain('Cliente Tienda X');
    expect($factura->cliente->nombre)->toBe('Cliente Tienda X');

    // Facturar el plan una segunda vez a la misma empresa no debe duplicar
    // el Actor cliente (mismo identificador estable).
    $segundaFactura = app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo');
    expect($segundaFactura->cliente_id)->toBe($factura->cliente_id);
});

test('facturar electronicamente sin NIT de la empresa cliente lanza un error claro', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa']);
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    User::factory()->create(['tipo_usuario' => 'empresa', 'es_empresa_emisora' => true]);

    $plan = Plan::create(['nombre' => 'Plan de prueba', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'plan_id' => $plan->id, 'valor_plan_total' => 330000]);
    // Sin ConfiguracionEmpresa -> sin NIT.

    expect(fn () => app(FacturarPlanService::class)->facturar($empresaCliente, 'electronica', 'efectivo'))
        ->toThrow(RuntimeException::class, 'primero completa el NIT');
});

test('marcar una empresa como emisora desmarca cualquier otra que ya lo fuera', function () {
    $emisoraVieja = User::factory()->create(['tipo_usuario' => 'empresa', 'es_empresa_emisora' => true]);
    $emisoraNueva = User::factory()->create(['tipo_usuario' => 'empresa', 'es_empresa_emisora' => false]);

    // Simula lo que hace el afterStateUpdated() del toggle en el formulario.
    User::where('es_empresa_emisora', true)
        ->whereKeyNot($emisoraNueva->id)
        ->update(['es_empresa_emisora' => false]);
    $emisoraNueva->update(['es_empresa_emisora' => true]);

    expect($emisoraVieja->fresh()->es_empresa_emisora)->toBeFalse();
    expect($emisoraNueva->fresh()->es_empresa_emisora)->toBeTrue();
});
