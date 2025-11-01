<!DOCTYPE html>
<html lang="es" style="height: 100%; overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <title>Punto de Venta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body style="height: 100vh; margin: 0; padding: 0; overflow: hidden; background: #f3f4f6; color: #111827;">

    {{-- 🔒 Botón de logout --}}
    <div style="position: fixed; top: 10px; right: 20px; z-index: 50;">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded shadow">
                Cerrar sesión
            </button>
        </form>
    </div>

    {{-- ✅ TÍTULO PUNTO DE VENTA --}}
    <div style="width: 100%; height: 60px; background: #2563eb; display: flex; align-items: center; justify-content: center; border-bottom: 2px solid #1d4ed8; flex-shrink: 0;">
        <h1 style="color: white; font-size: 24px; font-weight: bold; margin: 0; text-align: center;">
            {{ $nombreEmpresa }}
        </h1>
    </div>

    {{-- ✅ CONTENEDOR PARA EL CONTENIDO --}}
    <div style="height: calc(100vh - 60px); overflow: hidden;">
        @yield('content')
    </div>

    @livewireScripts
    
<script>
Livewire.on('caja-abierta', () => {
    Livewire.dispatch('refresh');
});
</script>


    <script>
Livewire.on('imprimir-cierre-caja', function(cajaId) {
    if (cajaId) {
        window.open('/ticket-cierre-caja/' + cajaId, '_blank', 'width=420,height=680');
    }
});
</script>
    <script>
  // ventana a reutilizar
  window.printWin = null;

  document.addEventListener('livewire:init', () => {
    Livewire.on('open-print', ({url}) => {
      console.log('[POS] OPEN PRINT URL:', url);
      // si ya abrimos la ventana en el click, úsala
      if (window.printWin && !window.printWin.closed) {
        window.printWin.location = url;
        window.printWin.focus();
      } else {
        // respaldo si no existe
        window.open(url, '_blank', 'width=400,height=600');
      }
    });
  });
</script>
<script src="https://unpkg.com/alpinejs" defer></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>