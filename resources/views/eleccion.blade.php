@extends('layouts.pos') {{-- usa el mismo layout del POS para mantener estilo --}}

@section('content')
<div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white shadow rounded p-8 text-center space-y-6 w-[400px]">
        <h1 class="text-2xl font-bold text-gray-700">¿A dónde quieres ir?</h1>

        <div class="flex flex-col gap-4">
            <a href="{{ route('pos') }}"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm shadow">
                🛒 Punto de Venta
            </a>

            <a href="{{ route('filament.admin.pages.dashboard') }}"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm shadow">
                ⚙️ Panel Administrativo
            </a>
        </div>
    </div>
</div>
@endsection
