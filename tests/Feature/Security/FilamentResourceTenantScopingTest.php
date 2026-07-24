<?php

use App\Filament\Resources\CarteraResource;
use App\Filament\Resources\CompraResource;
use App\Filament\Resources\CuentaContableResource;
use App\Filament\Resources\GastoResource;
use App\Filament\Resources\HotelHabitacionResource;
use App\Filament\Resources\MecanicoResource;
use App\Filament\Resources\NotaCreditoResource;
use App\Models\Actor;
use App\Models\Compra;
use App\Models\CuentaContable;
use App\Models\Factura;
use App\Models\Gasto;
use App\Models\HotelHabitacion;
use App\Models\Mecanico;
use App\Models\NotaCredito;
use App\Models\User;

// Regresion de la vulnerabilidad IDOR corregida: las paginas de edicion de
// estos resources no tenian getEloquentQuery() propio, asi que Filament
// resolvia el registro con la consulta global (sin filtrar por empresa_id).
// La lista (table()) si filtraba, pero eso no protegia la URL directa de
// edicion contra otra empresa.
//
// Las tablas de referencia que UserObserver necesita al crear una empresa
// (tipos_documento, departamentos, ciudades) se siembran globalmente en
// tests/Pest.php antes de cada test de Feature.

test('MecanicoResource::getEloquentQuery no expone mecanicos de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $mecanicoB = Mecanico::create([
        'empresa_id' => $empresaB->id,
        'nombre' => 'Mecanico de empresa B',
    ]);

    $this->actingAs($empresaA);

    expect(MecanicoResource::getEloquentQuery()->find($mecanicoB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(MecanicoResource::getEloquentQuery()->find($mecanicoB->id))->not->toBeNull();
});

test('CuentaContableResource::getEloquentQuery no expone cuentas de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    // Codigo fuera del PUC base que UserObserver crea automaticamente para
    // cada empresa nueva (CuentasContablesPucSeeder::cuentasBase()) -- si
    // se usa un codigo real como '1105' choca con la cuenta que ya existe.
    $cuentaB = CuentaContable::create([
        'empresa_id' => $empresaB->id,
        'codigo' => '9999',
        'nombre' => 'Cuenta de prueba de empresa B',
    ]);

    $this->actingAs($empresaA);

    expect(CuentaContableResource::getEloquentQuery()->find($cuentaB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(CuentaContableResource::getEloquentQuery()->find($cuentaB->id))->not->toBeNull();
});

test('HotelHabitacionResource::getEloquentQuery no expone habitaciones de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $habitacionB = HotelHabitacion::create([
        'empresa_id' => $empresaB->id,
        'numero' => '101',
    ]);

    $this->actingAs($empresaA);

    expect(HotelHabitacionResource::getEloquentQuery()->find($habitacionB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(HotelHabitacionResource::getEloquentQuery()->find($habitacionB->id))->not->toBeNull();
});

// Ampliacion del mismo caso de prueba a los recursos que manejan dinero
// directamente (gastos, compras, notas credito, cartera/facturas) --
// pedido explicito antes de pasar a produccion.

test('GastoResource::getEloquentQuery no expone gastos de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $gastoB = Gasto::create([
        'id_gasto' => 1,
        'empresa_id' => $empresaB->id,
        'tipo' => 'salida',
        'categoria' => 'otros',
        'descripcion' => 'Gasto de empresa B',
        'monto' => 50000,
        'fecha' => now()->toDateString(),
    ]);

    $this->actingAs($empresaA);

    expect(GastoResource::getEloquentQuery()->find($gastoB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(GastoResource::getEloquentQuery()->find($gastoB->id))->not->toBeNull();
});

test('CompraResource::getEloquentQuery no expone compras de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    // tipo_documento_id 6 = NIT, ya sembrado por TiposDocumentoSeeder (el
    // UserObserver depende de esa misma fila al crear el "CONSUMIDOR FINAL"
    // de cada empresa nueva).
    $proveedorB = Actor::create([
        'empresa_id' => $empresaB->id,
        'id_clip_pro' => 1,
        'tipo' => 2,
        'tipo_documento_id' => 6,
        'nombre' => 'Proveedor de empresa B',
    ]);

    $compraB = Compra::create([
        'empresa_id' => $empresaB->id,
        'proveedor_id' => $proveedorB->id,
    ]);

    $this->actingAs($empresaA);

    expect(CompraResource::getEloquentQuery()->find($compraB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(CompraResource::getEloquentQuery()->find($compraB->id))->not->toBeNull();
});

test('NotaCreditoResource::getEloquentQuery no expone notas credito de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $notaB = NotaCredito::create([
        'empresa_id' => $empresaB->id,
        'numero' => 'NC-000001',
        'total' => 10000,
    ]);

    $this->actingAs($empresaA);

    expect(NotaCreditoResource::getEloquentQuery()->find($notaB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(NotaCreditoResource::getEloquentQuery()->find($notaB->id))->not->toBeNull();
});

test('CarteraResource::getEloquentQuery no expone facturas de otra empresa', function () {
    $empresaA = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresaB = User::factory()->create(['tipo_usuario' => 'empresa']);

    $facturaB = Factura::create([
        'empresa_id' => $empresaB->id,
        'user_id' => $empresaB->id,
    ]);

    $this->actingAs($empresaA);

    expect(CarteraResource::getEloquentQuery()->find($facturaB->id))->toBeNull();

    $this->actingAs($empresaB);

    expect(CarteraResource::getEloquentQuery()->find($facturaB->id))->not->toBeNull();
});
