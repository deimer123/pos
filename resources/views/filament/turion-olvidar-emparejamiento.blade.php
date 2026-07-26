@if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite')
    <div class="mt-4 text-center">
        <form method="POST" action="{{ route('turion.olvidar-emparejamiento') }}"
            onsubmit="return confirm('¿Seguro que quieres olvidar el emparejamiento? Esta terminal va a pedir el código de emparejamiento otra vez.');">
            @csrf
            <button type="submit" class="text-xs text-gray-500 hover:text-gray-700 underline">
                🔌 Olvidar emparejamiento (cambiar de empresa)
            </button>
        </form>
    </div>
@endif
