<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\ServicioTecnicoOrden;
use App\Models\User;
use App\Services\Turion\ServicioTecnicoSyncPull;
use Laravel\Sanctum\Sanctum;

// Calco de TallerSyncPullTest -- ver ese archivo para el detalle de cada
// caso. servidor_id ya es parte del esquema de servicio_tecnico_ordenes
// desde la migracion inicial (a diferencia de taller_ordenes, que lo sumo
// despues), asi que no hace falta el hack de Schema::hasColumn.

function crearEmpresaServicioTecnicoPullTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa servicio tecnico pull test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'servicio_tecnico',
        'usa_servicio_tecnico' => true,
    ]);

    return $empresa;
}

test('una orden de servicio tecnico remota nueva se crea localmente con su servidor_id', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();

    ServicioTecnicoSyncPull::fusionar([
        [
            'id' => 801,
            'cliente_id' => null,
            'cliente_nombre' => 'Cliente Remoto',
            'cliente_telefono' => '3001234567',
            'marca' => 'Samsung',
            'modelo' => 'Galaxy A54',
            'imei_serial' => '123456789012345',
            'color' => 'Negro',
            'clave_desbloqueo' => '1234',
            'diagnostico' => 'Pantalla rota',
            'observaciones' => null,
            'estado' => 'en_proceso',
            'fecha_entrega_estimada' => null,
            'items' => [
                ['producto_id' => null, 'nombre' => 'Cambio de pantalla', 'cantidad' => 1, 'precio' => 180000],
            ],
        ],
    ], $empresa->id);

    $orden = ServicioTecnicoOrden::where('empresa_id', $empresa->id)->where('servidor_id', 801)->first();

    expect($orden)->not->toBeNull();
    expect($orden->imei_serial)->toBe('123456789012345');
    expect($orden->estado)->toBe('en_proceso');
    expect($orden->items)->toHaveCount(1);
    expect((float) $orden->items->first()->subtotal)->toBe(180000.0);
});

test('una orden de servicio tecnico remota ya bajada antes se actualiza, no se duplica', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();

    $payload = fn (string $estado) => [[
        'id' => 802,
        'cliente_id' => null,
        'cliente_nombre' => 'Cliente Remoto',
        'marca' => null, 'modelo' => null, 'imei_serial' => 'XYZ999',
        'color' => null, 'clave_desbloqueo' => null,
        'diagnostico' => null, 'observaciones' => null,
        'estado' => $estado,
        'fecha_entrega_estimada' => null,
        'items' => [],
    ]];

    ServicioTecnicoSyncPull::fusionar($payload('pendiente'), $empresa->id);
    ServicioTecnicoSyncPull::fusionar($payload('listo'), $empresa->id);

    expect(ServicioTecnicoOrden::where('empresa_id', $empresa->id)->where('servidor_id', 802)->count())->toBe(1);
    expect(ServicioTecnicoOrden::where('empresa_id', $empresa->id)->where('servidor_id', 802)->first()->estado)->toBe('listo');
});

test('una orden de servicio tecnico local con servidor_id que ya no viene en la lista remota se borra', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();

    $orden = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => 999,
        'numero_orden' => 1,
        'cliente_nombre' => 'Ya entregada en el droplet',
        'imei_serial' => 'AAA111',
        'estado' => 'pendiente',
    ]);

    ServicioTecnicoSyncPull::fusionar([], $empresa->id);

    expect(ServicioTecnicoOrden::find($orden->id))->toBeNull();
});

test('una orden de servicio tecnico local creada offline (sin servidor_id) no se toca', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();

    $local = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'servidor_id' => null,
        'numero_orden' => 1,
        'cliente_nombre' => 'Creada offline, aun no subida',
        'imei_serial' => 'BBB222',
        'estado' => 'pendiente',
    ]);

    ServicioTecnicoSyncPull::fusionar([], $empresa->id);

    expect(ServicioTecnicoOrden::find($local->id))->not->toBeNull();
});

test('cliente_id inexistente localmente se guarda como null', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();

    ServicioTecnicoSyncPull::fusionar([
        [
            'id' => 804,
            'cliente_id' => 999999,
            'cliente_nombre' => 'Cliente',
            'marca' => null, 'modelo' => null, 'imei_serial' => 'EEE555',
            'color' => null, 'clave_desbloqueo' => null,
            'diagnostico' => null, 'observaciones' => null,
            'estado' => 'pendiente',
            'fecha_entrega_estimada' => null,
            'items' => [],
        ],
    ], $empresa->id);

    $orden = ServicioTecnicoOrden::where('empresa_id', $empresa->id)->where('servidor_id', 804)->first();

    expect($orden->cliente_id)->toBeNull();
});

test('PairingController::ordenesServicioTecnico() solo devuelve las activas, con sus items', function () {
    $empresa = crearEmpresaServicioTecnicoPullTest();
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    $activa = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'numero_orden' => 1,
        'cliente_nombre' => 'Activa',
        'imei_serial' => 'FFF666',
        'estado' => 'en_proceso',
    ]);
    $activa->items()->create([
        'producto_id' => null, 'descripcion' => 'Item', 'cantidad' => 1,
        'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);
    ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'numero_orden' => 2,
        'cliente_nombre' => 'Ya entregada',
        'imei_serial' => 'GGG777',
        'estado' => 'entregado',
    ]);

    $respuesta = $this->getJson('/api/pairing/ordenes-servicio-tecnico')->assertOk();

    $ordenes = $respuesta->json('ordenes');

    expect($ordenes)->toHaveCount(1);
    expect($ordenes[0]['id'])->toBe($activa->id);
    expect($ordenes[0]['items'])->toHaveCount(1);
});
