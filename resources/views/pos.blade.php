@extends('layouts.pos')

@section('content')

<link rel="stylesheet" href="{{ asset('css/pos-facturas-table.css') }}?v={{ filemtime(public_path('css/pos-facturas-table.css')) }}">
<script defer src="{{ asset('js/pos-prefactura-prefix.js') }}?v={{ filemtime(public_path('js/pos-prefactura-prefix.js')) }}"></script>

@php
    $empresaId = auth()->user()->getEmpresaActualId();
    $configEmpresa = \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
    $usaMesas = (bool) ($configEmpresa?->usa_mesas ?? false);

    // 'modo' permite al admin_empresa forzar una variante especifica desde
    // la pantalla de Eleccion, sin depender de la configuracion real de la
    // empresa. Sin 'modo' se conserva el comportamiento automatico normal.
    $modo = request()->query('modo');
    $domiciliosForzado = null;

    if ($modo === 'normal') {
        $mostrarMesas = false;
    } elseif ($modo === 'mesas') {
        $mostrarMesas = true;
        $domiciliosForzado = false;
    } elseif ($modo === 'domicilios') {
        $mostrarMesas = true;
        $domiciliosForzado = true;
    } else {
        $mostrarMesas = $usaMesas;
    }
@endphp

@if($mostrarMesas)
    {{-- Modo restaurante: pantalla completa de mesas --}}
    <div style="width:100%; height:100%;">
        @livewire('panel-mesas', ['domiciliosForzado' => $domiciliosForzado], key('panel-mesas-main-' . ($modo ?? 'auto')))
    </div>
@else
    {{-- Modo tienda/almacén: POS normal --}}
    <div class="pos-shell" x-data="{ posTab: 'productos' }">
        <div class="pos-mobile-tabs">
            <button type="button" class="pos-mobile-tab"
                :class="{ 'is-active': posTab === 'productos' }"
                @click="posTab = 'productos'">Productos</button>
            <button type="button" class="pos-mobile-tab"
                :class="{ 'is-active': posTab === 'carrito' }"
                @click="posTab = 'carrito'">Carrito</button>
        </div>
        <div class="pos-layout">
            <div class="pos-pane pos-products-pane" :class="{ 'pos-mobile-hidden': posTab !== 'productos' }">
                @livewire('pos-productos', [], key('pos-productos-main'))
            </div>
            <div class="pos-pane pos-cart-pane" :class="{ 'pos-mobile-hidden': posTab !== 'carrito' }">
                <div class="flex flex-col h-full min-h-0 overflow-hidden">
                    @livewire('carrito-venta', [], key('carrito-venta-main'))
                </div>
            </div>
        </div>
    </div>
@endif

@endsection
