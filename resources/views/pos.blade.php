@extends('layouts.pos')

@section('content')
<div style="width:100%;height:100%;display:flex;flex-direction:row;margin:0;padding:0;overflow:hidden;">
  {{-- Columna izquierda --}}
  <div style="width:45%;height:100%;display:flex;flex-direction:column;border-right:1px solid #ccc;background:#fff;overflow:hidden;">
    @livewire('pos-productos')
  </div>

  {{-- Columna derecha --}}
  <div style="width:55%;height:100%;display:flex;flex-direction:column;background:#f8f9fa;overflow:hidden;">
    <!-- 👉 Wrapper que da altura al componente -->
    <div class="flex flex-col h-full min-h-0 overflow-hidden">
      @livewire('carrito-venta')
    </div>
  </div>
</div>
@endsection

