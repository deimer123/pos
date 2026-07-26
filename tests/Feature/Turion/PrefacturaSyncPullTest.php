<?php

use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Prefactura;
use App\Models\Product;
use App\Models\User;
use App\Services\Turion\PrefacturaSyncPull;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

// PrefacturaSyncPull::fusionar() es el lado de Turion que recibe la lista
// de prefacturas activas del droplet (PairingController::prefacturas()) y
// la mezcla con lo que ya existe en la base de datos local -- sin esto,
// una prefactura creada directo en el droplet (o por otra terminal) nunca
// aparecia en Turion ni con "Sincronizar".
//
// servidor_id solo existe en el esquema de Turion (sqlite); aqui se agrega
// a mano sobre la tabla de pruebas (mysql) porque fusionar() no depende
// del motor de base de datos, solo necesita la columna.

beforeEach(function () {
    if (! Schema::hasColumn('prefacturas', 'servidor_id')) {
        Schema::table('prefacturas', function ($table) {
            $table->unsignedBigInteger('servidor_id')->nullable()->after('id');
        });
    }
});

afterEach(function () {
    if (Schema::hasColumn('prefacturas', 'servidor_id')) {
        Schema::table('prefacturas', function ($table) {
            $table->dropColumn('servidor_id');
        });
    }
});

function crearEmpresaPullTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa pull test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

function crearProductoPullTest(User $empresa): Product
{
    return Product::create([
        'id_producto' => 1,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto X',
        'precio_venta1' => 5000,
        'existencias' => 100,
    ]);
}

test('una prefactura remota nueva se crea localmente con su servidor_id', function () {
    $empresa = crearEmpresaPullTest();
    crearProductoPullTest($empresa);

    PrefacturaSyncPull::fusionar([
        [
            'id' => 501,
            'cliente_id' => null,
            'vendedor_id' => null,
            'observaciones' => 'Nota remota',
            'estado' => 'borrador',
            'items' => [
                ['producto_id' => 1, 'nombre' => 'Producto X', 'cantidad' => 2, 'precio' => 5000, 'descuento' => 0],
            ],
        ],
    ], $empresa->id);

    $prefactura = Prefactura::where('empresa_id', $empresa->id)->where('servidor_id', 501)->first();

    expect($prefactura)->not->toBeNull();
    expect($prefactura->observaciones)->toBe('Nota remota');
    expect($prefactura->productos)->toHaveCount(1);
    expect((float) $prefactura->productos->first()->subtotal)->toBe(10000.0);
});

test('una prefactura remota ya bajada antes se actualiza, no se duplica', function () {
    $empresa = crearEmpresaPullTest();
    crearProductoPullTest($empresa);

    $payload = fn (string $obs) => [[
        'id' => 502,
        'cliente_id' => null,
        'vendedor_id' => null,
        'observaciones' => $obs,
        'estado' => 'borrador',
        'items' => [
            ['producto_id' => 1, 'nombre' => 'Producto X', 'cantidad' => 1, 'precio' => 3000, 'descuento' => 0],
        ],
    ]];

    PrefacturaSyncPull::fusionar($payload('Version 1'), $empresa->id);
    PrefacturaSyncPull::fusionar($payload('Version 2'), $empresa->id);

    expect(Prefactura::where('empresa_id', $empresa->id)->where('servidor_id', 502)->count())->toBe(1);

    $prefactura = Prefactura::where('empresa_id', $empresa->id)->where('servidor_id', 502)->first();
    expect($prefactura->observaciones)->toBe('Version 2');
});

test('una prefactura local con servidor_id que ya no viene en la lista remota se borra', function () {
    $empresa = crearEmpresaPullTest();

    $prefactura = Prefactura::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 999,
        'observaciones' => 'Ya facturada en el droplet',
        'estado' => 'borrador',
    ]);

    PrefacturaSyncPull::fusionar([], $empresa->id);

    expect(Prefactura::find($prefactura->id))->toBeNull();
});

test('una prefactura local creada offline (sin servidor_id) no se toca', function () {
    $empresa = crearEmpresaPullTest();

    $local = Prefactura::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'observaciones' => 'Creada offline, aun no subida',
        'estado' => 'borrador',
    ]);

    PrefacturaSyncPull::fusionar([], $empresa->id);

    expect(Prefactura::find($local->id))->not->toBeNull();
});

test('cliente_id/vendedor_id inexistentes localmente se guardan como null', function () {
    $empresa = crearEmpresaPullTest();

    PrefacturaSyncPull::fusionar([
        [
            'id' => 503,
            'cliente_id' => 999999,
            'vendedor_id' => 999999,
            'observaciones' => '',
            'estado' => 'borrador',
            'items' => [],
        ],
    ], $empresa->id);

    $prefactura = Prefactura::where('empresa_id', $empresa->id)->where('servidor_id', 503)->first();

    expect($prefactura->cliente_id)->toBeNull();
    expect($prefactura->vendedor_id)->toBeNull();
});

test('PairingController::prefacturas() solo devuelve las borrador, con sus items', function () {
    $empresa = crearEmpresaPullTest();
    crearProductoPullTest($empresa);
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = Prefactura::create(['empresa_id' => $empresa->id, 'estado' => 'borrador', 'observaciones' => 'Activa']);
    $activa->productos()->create([
        'empresa_id' => $empresa->id, 'producto_id' => 1, 'descripcion_larga' => 'Item',
        'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000, 'descuento' => 0,
    ]);
    Prefactura::create(['empresa_id' => $empresa->id, 'estado' => 'vendida', 'observaciones' => 'Ya facturada']);

    $respuesta = $this->getJson('/api/pairing/prefacturas')->assertOk();

    $prefacturas = $respuesta->json('prefacturas');

    expect($prefacturas)->toHaveCount(1);
    expect($prefacturas[0]['id'])->toBe($activa->id);
    expect($prefacturas[0]['items'])->toHaveCount(1);
});
