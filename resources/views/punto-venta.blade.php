@extends('layouts.pos') {{-- Asegúrate que este layout existe --}}

@section('content')
<div class="container mx-auto px-4 py-6">



    <div class="flex flex-col lg:flex-row gap-4">
        {{-- Sección de Productos --}}

         <div class="w-full md:w-2/3">
            @livewire('pos-productos')
        </div>
        

        {{-- Carrito de compras lateral --}}
        <div class="w-full lg:w-1/3 h-screen sticky top-0">
            @livewire('carrito-venta')
        </div>

       
    </div>

</div>
@endsection
