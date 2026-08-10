<?php

use App\Livewire\CarritoVenta;
use App\Models\Actor;
use App\Models\ConfiguracionEmpresa;
use App\Models\Prefactura;
use App\Models\Product;
use App\Models\ProductoLote;
use App\Models\ProductoVariante;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Bug real encontrado en auditoria: guardar el carrito como prefactura
// (boton "Guardar") perdia la asociacion de variante/lote de cada item --
// prefactura_productos no tenia esas columnas -- asi que al recargar la
// prefactura y facturarla, se descontaba el stock TOTAL del producto en
// vez del de la variante/lote puntual (mismo bug ya corregido para
// "Agregar sin escanear" en el POS, pero via un camino distinto).

function crearEmpresaPrefacturaLoteTest(): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Droguería prefactura test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'drogueria',
        'usa_lotes' => true,
    ]);

    return $empresa;
}

test('guardar y recargar una prefactura con un item de lote conserva la asociacion y factura el lote correcto', function () {
    $empresa = crearEmpresaPrefacturaLoteTest();

    $producto = Product::create([
        'id_producto' => 5501,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Acetaminofén 500mg',
        'precio_venta1' => 5000,
        'existencias' => 8,
        'iva_venta' => 0,
    ]);

    $loteA = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '5501A', 'lote' => 'LOTE-A', 'fecha_vencimiento' => '2027-01-01', 'stock' => 5,
    ]);
    $loteB = ProductoLote::create([
        'empresa_id' => $empresa->id, 'product_id' => $producto->id,
        'codigo' => '5501B', 'lote' => 'LOTE-B', 'fecha_vencimiento' => '2027-06-01', 'stock' => 3,
    ]);

    $cliente = Actor::create([
        'empresa_id' => $empresa->id,
        'id_clip_pro' => 50,
        'tipo' => 1,
        'tipo_persona' => 'natural',
        'tipo_documento_id' => 6,
        'nombre' => 'Cliente de prueba',
    ]);

    $componente = Livewire::actingAs($empresa)->test(CarritoVenta::class);

    // Simula haber escaneado el codigo del LOTE-A (mismo mecanismo del
    // POS: agregarProducto($id, $varianteId=null, $loteId)).
    $componente->call('agregarProducto', $producto->id_producto, null, $loteA->id);
    $componente->set('clienteId', $cliente->id);
    $componente->call('guardarPrefacturaConfirmada');

    $prefactura = Prefactura::where('empresa_id', $empresa->id)->latest()->firstOrFail();
    $itemGuardado = $prefactura->productos()->first();
    expect($itemGuardado->producto_lote_id)->toBe($loteA->id);

    // Nuevo componente (como si el cajero volviera despues) recarga la
    // prefactura y factura -- debe descontar SOLO el LOTE-A, no el total
    // del producto ni el LOTE-B.
    $componente2 = Livewire::actingAs($empresa)->test(CarritoVenta::class);
    $componente2->call('cargarPrefacturaAlCarrito', $prefactura->id);

    $carritoItem = collect($componente2->get('carrito'))->first();
    expect($carritoItem['producto_lote_id'])->toBe($loteA->id);

    $componente2->call('facturarConfirmada');

    expect((float) $loteA->fresh()->stock)->toBe(4.0);
    expect((float) $loteB->fresh()->stock)->toBe(3.0);
});
