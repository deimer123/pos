<?php

use App\Models\User;
use App\Services\Grupo\GrupoEmpresas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Base del grupo de empresas (sucursales como empresas ligadas a un dueno).
// Todo es aditivo: empresa_matriz_id NULL = empresa independiente de siempre.

function empresaGrupoTest(array $extra = []): User
{
    return User::factory()->create(array_merge(['tipo_usuario' => 'empresa', 'tipo_edicion' => 'online'], $extra));
}

test('users tiene empresa_matriz_id nullable e indexada', function () {
    expect(Schema::hasColumn('users', 'empresa_matriz_id'))->toBeTrue();

    $columna = DB::selectOne(
        'select is_nullable as nullable from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
        ['users', 'empresa_matriz_id']
    );
    $indice = DB::selectOne(
        'select count(*) as total from information_schema.statistics where table_schema = database() and table_name = ? and column_name = ?',
        ['users', 'empresa_matriz_id']
    );

    expect($columna->nullable)->toBe('YES')
        ->and((int) $indice->total)->toBeGreaterThan(0);
});

test('una empresa nueva es independiente', function () {
    $empresa = empresaGrupoTest();

    expect($empresa->fresh()->empresa_matriz_id)->toBeNull()
        ->and($empresa->esSucursal())->toBeFalse()
        ->and($empresa->sucursales)->toBeEmpty();
});

test('las relaciones matriz y sucursales funcionan', function () {
    $matriz = empresaGrupoTest();
    $norte = empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $sur = empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);

    expect($norte->esSucursal())->toBeTrue()
        ->and($norte->matriz->id)->toBe($matriz->id)
        ->and($matriz->sucursales->pluck('id')->sort()->values()->all())->toBe([$norte->id, $sur->id])
        ->and($matriz->esSucursal())->toBeFalse();
});

test('se puede ligar una empresa online a otra online', function () {
    $matriz = empresaGrupoTest();
    $empresa = empresaGrupoTest();

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($empresa, $matriz))->toBeNull();
});

test('quitar la liga siempre es valido', function () {
    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal(empresaGrupoTest(), null))->toBeNull();
});

test('no se puede ligar una empresa consigo misma', function () {
    $empresa = empresaGrupoTest();

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($empresa, $empresa))->not->toBeNull();
});

test('no se permiten cadenas: la matriz no puede ser sucursal', function () {
    $matriz = empresaGrupoTest();
    $norte = empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $otra = empresaGrupoTest();

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($otra, $norte))->not->toBeNull();
});

test('una empresa que ya tiene sucursales no puede volverse sucursal', function () {
    $matriz = empresaGrupoTest();
    empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $otra = empresaGrupoTest();

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($matriz, $otra))->not->toBeNull();
});

test('solo entre empresas de edicion online', function (string $edicionEmpresa, string $edicionMatriz) {
    $matriz = empresaGrupoTest(['tipo_edicion' => $edicionMatriz]);
    $empresa = empresaGrupoTest(['tipo_edicion' => $edicionEmpresa]);

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($empresa, $matriz))->not->toBeNull();
})->with([
    ['hibrida', 'online'],
    ['online', 'hibrida'],
    ['local', 'online'],
]);

test('solo las empresas pueden ser matriz o sucursal', function () {
    $matriz = empresaGrupoTest();
    $empleado = User::factory()->create(['tipo_usuario' => 'empleado', 'empresa_id' => $matriz->id]);

    expect(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal($empleado, $matriz))->not->toBeNull()
        ->and(app(GrupoEmpresas::class)->motivoNoPuedeSerSucursal(empresaGrupoTest(), $empleado))->not->toBeNull();
});

test('idsDelGrupo devuelve matriz y sucursales, y solo las de su grupo', function () {
    $matriz = empresaGrupoTest();
    $norte = empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $sur = empresaGrupoTest(['empresa_matriz_id' => $matriz->id]);
    $ajena = empresaGrupoTest();
    empresaGrupoTest(['empresa_matriz_id' => $ajena->id]);

    $esperado = [$matriz->id, $norte->id, $sur->id];
    sort($esperado);

    $servicio = app(GrupoEmpresas::class);

    expect($servicio->idsDelGrupo($matriz))->toBe($esperado)
        ->and($servicio->idsDelGrupo($norte))->toBe($esperado)
        ->and($servicio->idsDelGrupo($sur))->toBe($esperado);
});

test('una empresa independiente forma un grupo de uno', function () {
    $empresa = empresaGrupoTest();

    expect(app(GrupoEmpresas::class)->idsDelGrupo($empresa))->toBe([$empresa->id]);
});
