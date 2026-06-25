@extends('layouts.pos')

@section('header-left')
    {{-- Botón volver a mesas --}}
    <a href="{{ route('pos') }}" class="pos-btn-mesas"
       style="background:rgba(255,255,255,.15); color:white; border:1px solid rgba(255,255,255,.4); border-radius:8px; padding:5px 12px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; flex-shrink:0;">
        ← Mesas
    </a>
    {{-- Nombre de mesa (oculto en móvil muy pequeño, se ve en la barra central) --}}
    <span class="pos-mesa-nombre-label" style="color:white; font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        🪑 {{ $mesa->nombre }}
        @if($mesa->zona)
            <span style="font-size:11px; font-weight:400; opacity:.8;">({{ $mesa->zona }})</span>
        @endif
    </span>
    {{-- Chip usuario — oculto en móvil (aparece en la segunda fila del header móvil) --}}
    @php
        $usuario = auth()->user();
        $roleLabels = ['admin_empresa'=>'Admin','cajero'=>'Cajero','vendedor'=>'Vendedor','mesero'=>'Mesero','digitador'=>'Digitador','cocina'=>'Cocina'];
        $rolActual = collect($roleLabels)->first(fn($label, $role) => $usuario->hasRole($role));
    @endphp
    <span class="pos-header-user-chip" style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); border-radius:20px; padding:3px 10px; font-size:11px; color:white; white-space:nowrap;">
        👤 {{ $usuario->name }}@if($rolActual) · {{ $rolActual }}@endif
    </span>
@endsection

@section('content')

<link rel="stylesheet" href="{{ asset('css/pos-pro.css') }}?v={{ filemtime(public_path('css/pos-pro.css')) }}">
<link rel="stylesheet" href="{{ asset('css/pos-facturas-table.css') }}?v={{ filemtime(public_path('css/pos-facturas-table.css')) }}">
<script defer src="{{ asset('js/pos-prefactura-prefix.js') }}?v={{ filemtime(public_path('js/pos-prefactura-prefix.js')) }}"></script>

<div class="pos-shell" x-data="{ posTab: 'productos' }">

    {{-- Tabs mobile --}}
    <div class="pos-mobile-tabs">
        <button type="button" class="pos-mobile-tab"
            :class="{ 'is-active': posTab === 'productos' }"
            @click="posTab = 'productos'">Productos</button>
        <button type="button" class="pos-mobile-tab"
            :class="{ 'is-active': posTab === 'carrito' }"
            @click="posTab = 'carrito'">Comanda</button>
    </div>

    {{-- POS layout --}}
    <div class="pos-layout" style="flex:1; min-height:0;">
        <div class="pos-pane pos-products-pane" :class="{ 'pos-mobile-hidden': posTab !== 'productos' }">
            @livewire('pos-productos', [], key('pos-mesa-productos-'.$mesa->id))
        </div>
        <div class="pos-pane pos-cart-pane" :class="{ 'pos-mobile-hidden': posTab !== 'carrito' }">
            <div class="flex flex-col min-h-full overflow-y-auto">
                @livewire('carrito-venta', ['mesaId' => $mesa->id], key('carrito-mesa-'.$mesa->id))
            </div>
        </div>
    </div>
</div>

@endsection
