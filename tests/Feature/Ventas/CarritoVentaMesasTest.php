<?php

use App\Livewire\CarritoVenta;
use App\Models\ConfiguracionEmpresa;
use App\Models\Factura;
use App\Models\Mesa;
use App\Models\OrdenMesa;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Bar y Restaurante (mesas): no tenia ningun test directo del flujo real de
// "agregar producto a una mesa -> facturar" (solo habia tests de IDOR y de
// sincronizacion Turion). Cubre el camino de dinero real: agregar item crea
// la OrdenMesa/OrdenMesaItem y marca la mesa ocupada, facturar la cierra
// (estado=facturada) y libera la mesa.

function crearEmpresaMesasTest(): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Restaurante de prueba',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'bar_restaurante',
        'usa_mesas' => true,
    ]);

    return $empresa;
}

test('agregar un producto a una mesa crea la orden, marca la mesa ocupada y suma el total', function () {
    $empresa = crearEmpresaMesasTest();

    $mesa = Mesa::create([
        'empresa_id' => $empresa->id,
        'codigo' => 'M1',
        'nombre' => 'Mesa 1',
        'capacidad' => 4,
        'estado' => 'libre',
        'activo' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 7001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Hamburguesa clasica',
        'precio_venta1' => 25000,
        'existencias' => 100,
    ]);

    Livewire::actingAs($empresa)
        ->test(CarritoVenta::class, ['mesaId' => $mesa->id])
        ->call('agregarProducto', $producto->id_producto);

    $mesa->refresh();
    expect($mesa->estado)->toBe('ocupada');

    $orden = OrdenMesa::where('mesa_id', $mesa->id)->where('estado', 'abierta')->firstOrFail();
    expect((float) $orden->total)->toBe(25000.0);
    expect($orden->items()->count())->toBe(1);
});

test('facturar la cuenta de una mesa la cierra (estado=facturada) y libera la mesa', function () {
    $empresa = crearEmpresaMesasTest();

    $mesa = Mesa::create([
        'empresa_id' => $empresa->id,
        'codigo' => 'M2',
        'nombre' => 'Mesa 2',
        'capacidad' => 4,
        'estado' => 'libre',
        'activo' => true,
    ]);

    $producto = Product::create([
        'id_producto' => 7002,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Gaseosa',
        'precio_venta1' => 5000,
        'existencias' => 100,
        'iva_venta' => 0,
    ]);

    $componente = Livewire::actingAs($empresa)->test(CarritoVenta::class, ['mesaId' => $mesa->id]);
    $componente->call('agregarProducto', $producto->id_producto);
    $componente->call('facturarConfirmada');

    $mesa->refresh();
    expect($mesa->estado)->toBe('libre');

    $orden = OrdenMesa::where('mesa_id', $mesa->id)->latest()->firstOrFail();
    expect($orden->estado)->toBe('facturada');

    $factura = Factura::where('empresa_id', $empresa->id)->latest()->firstOrFail();
    expect((float) $factura->total)->toBe(5000.0);
});
