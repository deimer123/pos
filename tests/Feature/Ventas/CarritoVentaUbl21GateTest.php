<?php

use App\Livewire\CarritoVenta;
use App\Models\ConfiguracionEmpresa;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Bug real encontrado en produccion: CarritoVenta tenia su PROPIA copia
// privada de facturacionElectronicaDisponible() (solo miraba factus_enabled),
// duplicada de la de FacturarVentaService -- por eso, aunque el dispatcher
// de "facturar" ya soportaba el proveedor alterno, el boton "Electronica"
// del POS seguia sin aparecer para empresas configuradas solo con UBL21.
// Se corrigio para que delegue en FacturarVentaService::facturacionElectronicaDisponible().

function crearEmpresaCarritoUbl21GateTest(): User
{
    Role::firstOrCreate(['name' => 'admin_empresa', 'guard_name' => 'web']);

    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);
    $empresa->assignRole('admin_empresa');

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Empresa gate ubl21 test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'tienda',
        'factura_electronica_proveedor' => 'ubl21',
        'ubl21_base_url' => 'https://fake-ubl21.test/api/ubl2.1',
        'ubl21_api_token' => 'fake-token',
        'ubl21_prefix' => 'SETP',
        'ubl21_resolution_number' => '18760000001',
        'ubl21_numbering_from' => 1,
        'ubl21_numbering_to' => 5000,
    ]);

    Product::create([
        'id_producto' => 8001,
        'empresa_id' => $empresa->id,
        'id_familia1' => 1,
        'id_familia2' => 1,
        'descripcion_larga' => 'Producto POS test',
        'precio_venta1' => 57000,
        'existencias' => 10,
    ]);

    return $empresa;
}

test('el POS ofrece "Electronica" cuando la empresa solo tiene el proveedor alterno configurado (sin Factus)', function () {
    $empresa = crearEmpresaCarritoUbl21GateTest();

    Livewire::actingAs($empresa)
        ->test(CarritoVenta::class)
        ->call('agregarProducto', 8001)
        ->call('confirmarFacturar')
        ->assertDispatched('confirmar-facturar', factusHabilitado: true);
});
