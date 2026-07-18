<?php

use App\Filament\Resources\CuentaContableResource;
use App\Filament\Resources\HotelHabitacionResource;
use App\Filament\Resources\MecanicoResource;
use App\Models\CuentaContable;
use App\Models\HotelHabitacion;
use App\Models\Mecanico;
use App\Models\User;

// Regresion de la vulnerabilidad IDOR corregida: las paginas de edicion de
// estos resources no tenian getEloquentQuery() propio, asi que Filament
// resolvia el registro con la consulta global (sin filtrar por empresa_id).
// La lista (table()) si filtraba, pero eso no protegia la URL directa de
// edicion contra otra empresa.

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

    $cuentaB = CuentaContable::create([
        'empresa_id' => $empresaB->id,
        'codigo' => '1105',
        'nombre' => 'Caja general de empresa B',
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
