<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ventas\FacturarPlanService;
use Spatie\Permission\Models\Role;

// Cobro de plan: el super_admin le genera a una empresa una factura por
// lo que le corresponde pagar segun su plan. La "empresa emisora" es la
// MISMA cuenta con la que el super_admin inicia sesion (no una empresa
// aparte que hay que crear y marcar) -- sus propios datos de facturacion
// (NIT, representante legal) se configuran en su ConfiguracionEmpresa,
// igual que cualquier otra empresa. Reusa la misma tabla facturas/
// factura_detalles que usa cualquier empresa para sus propias ventas.

function crearSuperAdminFacturarPlanTest(bool $conConfiguracion = true): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['tipo_usuario' => 'empresa', 'name' => 'Mi Empresa SAS']);
    $superAdmin->assignRole('super_admin');

    if ($conConfiguracion) {
        ConfiguracionEmpresa::create([
            'empresa_id' => $superAdmin->id,
            'nombre_empresa' => 'Mi Empresa SAS',
            'representante_legal' => 'Yo Mismo',
            'tipo_negocio' => 'tienda',
        ]);
    }

    return $superAdmin;
}

test('facturar sin haber configurado los datos de facturacion del super_admin lanza un error claro', function () {
    $superAdmin = crearSuperAdminFacturarPlanTest(conConfiguracion: false);
    $this->actingAs($superAdmin);

    $plan = Plan::create(['nombre' => 'Plan de prueba', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'plan_id' => $plan->id, 'valor_plan_total' => 330000]);

    expect(fn () => app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo'))
        ->toThrow(RuntimeException::class, 'Todavía no configuraste tus datos de facturación');
});

test('un no super_admin no puede facturar el plan de una empresa', function () {
    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'valor_plan_total' => 330000]);
    $this->actingAs($empresaCliente);

    expect(fn () => app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo'))
        ->toThrow(RuntimeException::class, 'Solo un super_admin');
});

test('facturar el plan como salida de mercancia crea la factura a nombre de la cuenta del super_admin', function () {
    $superAdmin = crearSuperAdminFacturarPlanTest();
    $this->actingAs($superAdmin);

    $plan = Plan::create(['nombre' => 'Plan Emprende', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create([
        'tipo_usuario' => 'empresa',
        'name' => 'Cliente Tienda X',
        'plan_id' => $plan->id,
        'plan_meses' => 3,
        'valor_plan_total' => 330000,
    ]);

    $factura = app(FacturarPlanService::class)->facturar($empresaCliente, 'salida', 'efectivo');

    expect($factura->empresa_id)->toBe($superAdmin->id);
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
    $superAdmin = crearSuperAdminFacturarPlanTest();
    $this->actingAs($superAdmin);

    $plan = Plan::create(['nombre' => 'Plan de prueba', 'meses' => 3, 'precio' => 330000, 'usuarios_incluidos' => 1, 'activo' => true]);
    $empresaCliente = User::factory()->create(['tipo_usuario' => 'empresa', 'plan_id' => $plan->id, 'valor_plan_total' => 330000]);
    // Sin ConfiguracionEmpresa -> sin NIT.

    expect(fn () => app(FacturarPlanService::class)->facturar($empresaCliente, 'electronica', 'efectivo'))
        ->toThrow(RuntimeException::class, 'primero completa el NIT');
});
