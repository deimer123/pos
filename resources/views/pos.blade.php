@extends('layouts.pos')

@section('content')

<link rel="stylesheet" href="{{ asset('css/pos-pro.css') }}?v={{ filemtime(public_path('css/pos-pro.css')) }}">
<link rel="stylesheet" href="{{ asset('css/pos-facturas-table.css') }}?v={{ filemtime(public_path('css/pos-facturas-table.css')) }}">
<script defer src="{{ asset('js/pos-prefactura-prefix.js') }}?v={{ filemtime(public_path('js/pos-prefactura-prefix.js')) }}"></script>

<div class="pos-shell" x-data="{ posTab: 'productos' }">
  <div class="pos-mobile-tabs">
    <button type="button"
      class="pos-mobile-tab"
      :class="{ 'is-active': posTab === 'productos' }"
      @click="posTab = 'productos'">
      Productos
    </button>

    <button type="button"
      class="pos-mobile-tab"
      :class="{ 'is-active': posTab === 'carrito' }"
      @click="posTab = 'carrito'">
      Carrito
    </button>
  </div>

  <div class="pos-layout">
    {{-- Columna izquierda --}}
    <div class="pos-pane pos-products-pane" :class="{ 'pos-mobile-hidden': posTab !== 'productos' }">
      @livewire('pos-productos', [], key('pos-productos-main'))
    </div>

    {{-- Columna derecha --}}
    <div class="pos-pane pos-cart-pane" :class="{ 'pos-mobile-hidden': posTab !== 'carrito' }">
      <div class="flex flex-col h-full min-h-0 overflow-hidden">
        @livewire('carrito-venta', [], key('carrito-venta-main'))
      </div>
    </div>
  </div>
</div>

@endsection
