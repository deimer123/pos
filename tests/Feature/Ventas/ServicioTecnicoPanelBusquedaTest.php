<?php

use App\Livewire\ServicioTecnicoPanel;
use App\Models\ConfiguracionEmpresa;
use App\Models\ServicioTecnicoOrden;
use App\Models\User;
use Livewire\Livewire;

// El buscador del panel de Servicio Tecnico ya filtraba por IMEI/cliente/
// marca; se agrega numero_orden para que escanear el codigo de barras del
// ticket (que codifica justo ese numero, ver tickets/servicio-tecnico-
// etiqueta.blade.php) tambien encuentre la orden.

test('buscar por numero de orden encuentra la orden aunque el termino no coincida con IMEI/cliente/marca', function () {
    $empresa = User::factory()->create(['tipo_usuario' => 'empresa']);

    ConfiguracionEmpresa::create([
        'empresa_id' => $empresa->id,
        'nombre_empresa' => 'Celulares Test',
        'representante_legal' => 'Rep Legal',
        'tipo_negocio' => 'servicio_tecnico',
        'usa_servicio_tecnico' => true,
    ]);

    $ordenBuscada = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'cliente_nombre' => 'Juan Perez',
        'marca' => 'Samsung',
        'imei_serial' => '111111111111111',
        'estado' => 'en_proceso',
    ]);

    $otraOrden = ServicioTecnicoOrden::create([
        'empresa_id' => $empresa->id,
        'cliente_nombre' => 'Maria Gomez',
        'marca' => 'Apple',
        'imei_serial' => '222222222222222',
        'estado' => 'en_proceso',
    ]);

    Livewire::actingAs($empresa)
        ->test(ServicioTecnicoPanel::class)
        ->set('busqueda', (string) $ordenBuscada->numero_orden)
        ->assertSee('Juan Perez')
        ->assertDontSee('Maria Gomez');
});
