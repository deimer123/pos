<!DOCTYPE html>
<html lang="es" style="height: 100%; overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Punto de Venta</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body style="height: 100vh; margin: 0; padding: 0; overflow: hidden; background: #f3f4f6; color: #111827;">

    {{-- Boton de logout --}}
    <div class="pos-top-actions" x-data="{ menuOpen: false }" style="position: fixed; top: 10px; right: 20px; z-index: 50;">
      <button type="button" class="pos-mobile-menu-button" @click="menuOpen = !menuOpen" aria-label="Menu POS">
        Menu
      </button>

      @if(
    request()->routeIs('pos') &&
    auth()->user()->hasAnyRole(['digitador', 'admin_empresa'])
)

<div class="pos-actions-list flex gap-2" :class="{ 'is-open': menuOpen }">

    <a href="/admin"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm rounded shadow inline-block">
        Administracion
    </a>

    <form method="POST" action="{{ route('cerrar.sesion') }}">
        @csrf

        <button type="submit"
            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded shadow">
            Cerrar sesion
        </button>
    </form>

</div>

@else

<div class="pos-actions-list" :class="{ 'is-open': menuOpen }">
<form method="POST" action="{{ route('cerrar.sesion') }}">
    @csrf

    <button type="submit"
        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded shadow">
        Cerrar sesion
    </button>
</form>
</div>

@endif
    </div>

    {{-- Titulo punto de venta --}}
    <div class="pos-app-header" style="width: 100%; height: 60px; background: #2563eb; display: flex; align-items: center; justify-content: center; border-bottom: 2px solid #1d4ed8; flex-shrink: 0;">
        <h1 style="color: white; font-size: 24px; font-weight: bold; margin: 0; text-align: center;">
            {{ $nombreEmpresa }}
        </h1>
    </div>

    {{-- Contenedor para el contenido --}}
    <div style="height: calc(100vh - 60px); overflow: hidden;">
        @yield('content')
    </div>

       @livewireScripts

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/pos-responsive-actions.js') }}?v={{ filemtime(public_path('js/pos-responsive-actions.js')) }}"></script>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('imprimir-cierre-caja', (event) => {
                const cajaId = event?.cajaId ?? event?.[0] ?? event;

                if (cajaId) {
                    window.open('/ticket-cierre-caja/' + cajaId, '_blank', 'width=420,height=680');
                }
            });

            Livewire.on('open-print', (event) => {
                const url = event?.url ?? event?.[0]?.url ?? event;

                if (url) {
                    window.open(url, '_blank', 'width=400,height=600');
                }
            });
        });

        window.posCallCartAction = function (button, method, ...args) {
            const root = button?.closest?.('[wire\\:id]');
            const componentId = root ? root.getAttribute('wire:id') : null;

            if (!window.Livewire || !componentId) {
                console.warn('POS action ignored: Livewire component not ready', method);
                return;
            }

            const component = Livewire.find(componentId);
            if (!component || typeof component.call !== 'function') {
                console.warn('POS action ignored: Livewire component not found', method);
                return;
            }

            component.call(method, ...args);
        };

        window.posLimpiarCarrito = function (button) {
            if (!window.Swal) return;
            Swal.fire({
                title: 'Vaciar carrito?',
                text: 'Se eliminaran todos los productos.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Si, vaciar',
                cancelButtonText: 'Cancelar'
            }).then((r) => {
                if (!r.isConfirmed) return;
                window.posCallCartAction(button, 'limpiarCarrito');
            });
        };

        window.posGuardarPrefactura = function (button) {
            if (!window.Swal) return;
            const root = button?.closest?.('[wire\\:id]');
            const componentId = root ? root.getAttribute('wire:id') : null;
            const component = window.Livewire && componentId ? Livewire.find(componentId) : null;
            const carrito = component ? (component.get('carrito') ?? []) : [];

            if (carrito.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Carrito vacio', text: 'Debe agregar productos antes de guardar.' });
                return;
            }

            Swal.fire({
                title: 'Guardar prefactura?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, guardar',
                cancelButtonText: 'Cancelar'
            }).then((r) => {
                if (!r.isConfirmed) return;
                window.posCallCartAction(button, 'guardarPrefacturaConfirmada');
            });
        };

        window.posAbrirEditar = function (button) {
            if (!window.Swal) return;
            const root = button?.closest?.('[wire\\:id]');
            const componentId = root ? root.getAttribute('wire:id') : null;
            const component = window.Livewire && componentId ? Livewire.find(componentId) : null;
            const carrito = component ? (component.get('carrito') ?? []) : [];

            if (carrito.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Carrito vacio', text: 'Debe agregar productos antes de editar.' });
                return;
            }

            window.posCallCartAction(button, 'abrirModalEditar');
        };

        let tiempoInactividad = 30 * 60 * 1000;
        let temporizador;
        let sesionCerradaPorInactividad = false;

        function cerrarSesionPorInactividad() {
            if (sesionCerradaPorInactividad) return;

            sesionCerradaPorInactividad = true;
            clearTimeout(temporizador);

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

            fetch('{{ route('cerrar.sesion') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            }).finally(() => {
                window.location.replace('/admin/login?inactive=1');
            });
        }

        function reiniciarTemporizador() {
            if (sesionCerradaPorInactividad) return;

            clearTimeout(temporizador);

            temporizador = setTimeout(() => {
                cerrarSesionPorInactividad();
            }, tiempoInactividad);
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, reiniciarTemporizador, { passive: true });
        });

        document.addEventListener('livewire:init', function () {
            if (! window.Livewire?.hook) return;

            window.Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 401 || status === 419) {
                        preventDefault();
                        cerrarSesionPorInactividad();
                    }
                });
            });
        });

        reiniciarTemporizador();
    </script>

</body>
</html>
