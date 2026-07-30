<?php

use App\Models\ConfiguracionEmpresa;
use App\Models\HotelHabitacion;
use App\Models\HotelReserva;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\Prefactura;
use App\Models\Product;
use App\Models\TallerOrden;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// Borrar una prefactura, una orden de taller, cancelar una reserva de hotel
// o liberar una mesa OFFLINE en Turion nunca se subia al droplet -- el
// siguiente "Sincronizar" (que baja lo que el droplet todavia cree activo)
// volvia a traer lo que se acababa de borrar/cancelar localmente. Estos
// endpoints son el lado del droplet que faltaba (ver ColaSincronizacion::
// encolarPrefacturaBorrada/encolarTallerBorrado/encolarHotelCancelado/
// encolarMesaLiberada, y PosPush::subirOperacion()).

function crearEmpresaBorrarCancelarTest(): User
{
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa borrar/cancelar test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
    ]);

    return $empresa;
}

function cajeroAutenticadoBorrarCancelarTest(User $empresa): User
{
    $cajero = User::factory()->create(['empresa_id' => $empresa->id, 'tipo_usuario' => 'empleado']);
    Sanctum::actingAs($cajero, ['*']);

    return $cajero;
}

test('prefacturaBorrar() borra la prefactura y sus items en el droplet', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $producto = Product::create([
        'id_producto' => 1, 'empresa_id' => $empresa->id, 'id_familia1' => 1, 'id_familia2' => 1,
        'descripcion_larga' => 'Producto test', 'precio_venta1' => 1000, 'existencias' => 100,
    ]);

    $prefactura = Prefactura::create(['empresa_id' => $empresa->id, 'estado' => 'borrador']);
    $prefactura->productos()->create([
        'empresa_id' => $empresa->id, 'producto_id' => $producto->id_producto, 'descripcion_larga' => 'Item',
        'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000, 'descuento' => 0,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/prefactura/borrar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $prefactura->id,
    ]);

    $respuesta->assertOk();
    expect(Prefactura::find($prefactura->id))->toBeNull();
    expect(\Illuminate\Support\Facades\DB::table('prefactura_productos')->count())->toBe(0);
});

test('prefacturaBorrar() es idempotente por uuid', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $prefactura = Prefactura::create(['empresa_id' => $empresa->id, 'estado' => 'borrador']);
    $payload = ['uuid' => (string) Str::uuid(), 'servidor_id' => $prefactura->id];

    $this->postJson('/api/pairing/subir/prefactura/borrar', $payload)->assertOk();
    $this->postJson('/api/pairing/subir/prefactura/borrar', $payload)->assertOk();

    expect(Prefactura::find($prefactura->id))->toBeNull();
});

test('tallerBorrar() borra la orden y sus repuestos en el droplet', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 1,
        'cliente_nombre' => 'Cliente', 'placa' => 'ABC123', 'estado' => 'pendiente',
    ]);
    $orden->repuestos()->create([
        'producto_id' => null, 'descripcion' => 'Item', 'cantidad' => 1,
        'precio_unitario' => 1000, 'subtotal' => 1000,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/taller/borrar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
    ]);

    $respuesta->assertOk();
    expect(TallerOrden::find($orden->id))->toBeNull();
    expect(\Illuminate\Support\Facades\DB::table('taller_repuestos')->where('orden_id', $orden->id)->count())->toBe(0);
});

test('tallerBorrar() NO borra una orden que ya se facturo en el droplet mientras Turion estaba desconectado', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 1,
        'cliente_nombre' => 'Cliente', 'placa' => 'ABC123', 'estado' => 'entregado',
        'factura_id' => 999,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/taller/borrar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
    ]);

    $respuesta->assertOk();
    expect(TallerOrden::find($orden->id))->not->toBeNull();
});

test('hotelCancelar() marca la reserva como cancelada en el droplet', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '101']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 1,
        'huesped_nombre' => 'Huesped', 'fecha_checkin' => now()->toDateString(), 'estado' => 'checkin',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/hotel/cancelar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
    ]);

    $respuesta->assertOk();
    expect($reserva->fresh()->estado)->toBe('cancelada');
});

test('hotelCancelar() NO cancela una reserva que ya se facturo o hizo checkout en el droplet', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '102']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 2,
        'huesped_nombre' => 'Huesped', 'fecha_checkin' => now()->toDateString(), 'estado' => 'checkout',
        'factura_id' => 999,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/hotel/cancelar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
    ]);

    $respuesta->assertOk();
    expect($reserva->fresh()->estado)->toBe('checkout');
});

test('mesaLiberar() cancela la orden abierta de esa mesa en el droplet', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $mesa = Mesa::create(['empresa_id' => $empresa->id, 'codigo' => 'M1', 'nombre' => 'Mesa 1', 'estado' => 'ocupada']);
    $orden = OrdenMesa::create(['empresa_id' => $empresa->id, 'mesa_id' => $mesa->id, 'estado' => 'abierta']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/liberar', [
        'uuid' => (string) Str::uuid(),
        'mesa_id' => $mesa->id,
    ]);

    $respuesta->assertOk();
    expect($orden->fresh()->estado)->toBe('cancelada');
    expect($mesa->fresh()->estado)->toBe('libre');
});

test('mesaLiberar() no revienta si la mesa no tiene ninguna orden activa', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $mesa = Mesa::create(['empresa_id' => $empresa->id, 'codigo' => 'M2', 'nombre' => 'Mesa 2', 'estado' => 'libre']);

    $respuesta = $this->postJson('/api/pairing/subir/mesa/liberar', [
        'uuid' => (string) Str::uuid(),
        'mesa_id' => $mesa->id,
    ]);

    $respuesta->assertOk();
    expect($mesa->fresh()->estado)->toBe('libre');
});

test('hotelActualizar() edita los datos de una reserva que se edito offline en Turion', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '101']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 1,
        'huesped_nombre' => 'Nombre viejo', 'fecha_checkin' => now()->toDateString(), 'estado' => 'reservada',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/hotel/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
        'huesped_nombre' => 'Nombre nuevo',
        'observaciones' => 'Editado offline',
    ]);

    $respuesta->assertOk();
    $reserva->refresh();
    expect($reserva->huesped_nombre)->toBe('Nombre nuevo');
    expect($reserva->observaciones)->toBe('Editado offline');
    expect($reserva->estado)->toBe('reservada');
});

test('hotelActualizar() confirma un check-in que se hizo offline en Turion', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '102']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 2,
        'huesped_nombre' => 'Huesped', 'fecha_checkin' => now()->toDateString(), 'estado' => 'reservada',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/hotel/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
        'estado' => 'checkin',
        'checkin_real_at' => now()->toIso8601String(),
    ]);

    $respuesta->assertOk();
    expect($reserva->fresh()->estado)->toBe('checkin');
});

test('hotelActualizar() registra una salida anticipada hecha offline en Turion', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '103']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 3,
        'huesped_nombre' => 'Huesped', 'fecha_checkin' => now()->subDays(2)->toDateString(),
        'fecha_checkout' => now()->addDays(3)->toDateString(), 'estado' => 'checkin',
    ]);

    $hoy = now()->toDateString();

    $respuesta = $this->postJson('/api/pairing/subir/hotel/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
        'fecha_checkout' => $hoy,
    ]);

    $respuesta->assertOk();
    expect($reserva->fresh()->fecha_checkout->toDateString())->toBe($hoy);
});

test('hotelActualizar() NO toca una reserva que ya se facturo en el droplet mientras Turion estaba desconectado', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $habitacion = HotelHabitacion::create(['empresa_id' => $empresa->id, 'numero' => '104']);
    $reserva = HotelReserva::create([
        'empresa_id' => $empresa->id, 'habitacion_id' => $habitacion->id, 'numero_reserva' => 4,
        'huesped_nombre' => 'Ya facturada', 'fecha_checkin' => now()->toDateString(),
        'estado' => 'checkout', 'factura_id' => 999,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/hotel/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $reserva->id,
        'huesped_nombre' => 'Intento de editar tarde',
    ]);

    $respuesta->assertOk();
    expect($reserva->fresh()->huesped_nombre)->toBe('Ya facturada');
});

test('tallerActualizar() edita los datos de una orden que se edito offline en Turion', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 1,
        'cliente_nombre' => 'Cliente viejo', 'placa' => 'ABC123', 'estado' => 'pendiente',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/taller/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
        'cliente_nombre' => 'Cliente nuevo',
        'diagnostico' => 'Editado offline',
    ]);

    $respuesta->assertOk();
    $orden->refresh();
    expect($orden->cliente_nombre)->toBe('Cliente nuevo');
    expect($orden->diagnostico)->toBe('Editado offline');
    expect($orden->estado)->toBe('pendiente');
});

test('tallerActualizar() cambia el estado de una orden (cambiarEstado offline) incluyendo entregado_at', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 2,
        'cliente_nombre' => 'Cliente', 'placa' => 'XYZ789', 'estado' => 'listo',
    ]);

    $entregadoAt = now()->toIso8601String();

    $respuesta = $this->postJson('/api/pairing/subir/taller/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
        'estado' => 'entregado',
        'entregado_at' => $entregadoAt,
    ]);

    $respuesta->assertOk();
    $orden->refresh();
    expect($orden->estado)->toBe('entregado');
    expect($orden->entregado_at)->not->toBeNull();
});

test('tallerActualizar() guarda la nota de trabajo (guardarNotaTrabajo offline)', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 3,
        'cliente_nombre' => 'Cliente', 'placa' => 'DEF456', 'estado' => 'en_proceso',
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/taller/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
        'nota_trabajo' => 'Falta pieza',
    ]);

    $respuesta->assertOk();
    expect($orden->fresh()->nota_trabajo)->toBe('Falta pieza');
});

test('tallerActualizar() NO toca una orden que ya se facturo en el droplet mientras Turion estaba desconectado', function () {
    $empresa = crearEmpresaBorrarCancelarTest();
    cajeroAutenticadoBorrarCancelarTest($empresa);

    $orden = TallerOrden::create([
        'empresa_id' => $empresa->id, 'numero_orden' => 4,
        'cliente_nombre' => 'Ya facturado', 'placa' => 'GHI012', 'estado' => 'entregado',
        'factura_id' => 999,
    ]);

    $respuesta = $this->postJson('/api/pairing/subir/taller/actualizar', [
        'uuid' => (string) Str::uuid(),
        'servidor_id' => $orden->id,
        'cliente_nombre' => 'Intento de editar tarde',
    ]);

    $respuesta->assertOk();
    expect($orden->fresh()->cliente_nombre)->toBe('Ya facturado');
});
