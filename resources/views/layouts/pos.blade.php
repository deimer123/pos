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
    <div class="pos-top-actions" x-data="{ menuOpen: false }" style="position: fixed; top: 10px; right: 20px; z-index: 50; display:flex; align-items:center; gap:8px;">

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

    @php $usaTaller = (bool) \App\Models\ConfiguracionEmpresa::where('empresa_id', auth()->user()->getEmpresaActualId())->value('usa_taller'); @endphp
    @if($usaTaller)
    <a href="{{ route('taller') }}"
        class="inline-block text-white px-4 py-2 text-sm rounded shadow"
        style="background:#0f766e;">
        🔧 Taller
    </a>
    @endif

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
    @if(request()->routeIs('taller'))
    <a href="{{ route('pos') }}"
        class="inline-block text-white px-4 py-2 text-sm rounded shadow"
        style="background:#4338ca;">
        🛒 Ir al POS
    </a>
    @endif
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
    <div class="pos-app-header" style="width: 100%; height: 60px; background: #2563eb; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; border-bottom: 2px solid #1d4ed8; flex-shrink: 0;">
        {{-- Izquierda: botón ← Mesas (solo en vista mesa) o usuario logueado --}}
        <div class="pos-header-left" style="flex:1; display:flex; align-items:center; justify-content:flex-start; gap:8px; min-width:0;">
            @hasSection('header-left')
                @yield('header-left')
            @else
                @auth
                @php
                    $u = auth()->user();
                    $roleLabels = ['admin_empresa'=>'Admin','cajero'=>'Cajero','vendedor'=>'Vendedor','mesero'=>'Mesero','digitador'=>'Digitador'];
                    $rolActual = collect($roleLabels)->first(fn($l,$r) => $u->hasRole($r));
                @endphp
                <span style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); border-radius:20px; padding:3px 12px; font-size:12px; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;">
                    👤 {{ $u->name }}@if($rolActual) · {{ $rolActual }}@endif
                </span>
                @endauth
            @endif
        </div>
        {{-- Centro: nombre empresa --}}
        <h1 class="pos-header-empresa" style="color: white; font-size: 24px; font-weight: bold; margin: 0; text-align: center; flex:0 0 auto;">
            {{ $nombreEmpresa }}
        </h1>
        {{-- Derecha: espacio para balance visual --}}
        <div style="flex:1; display:flex; align-items:center; justify-content:flex-end; gap:8px;">
            @hasSection('mesa-nav')
                @yield('mesa-nav')
            @endif
        </div>
    </div>

    {{-- Contenedor para el contenido --}}
    <div style="height: calc(100vh - 60px); overflow: hidden;" class="pos-main-content">
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

        let tiempoInactividad = 30 * 60 * 1000;
        let temporizador;
        let sesionCerradaPorInactividad = false;

        function cerrarSesionPorInactividad() {
            if (sesionCerradaPorInactividad) return;
            // Si ya hay overlay de sesión duplicada, no redirigir por inactividad
            if (document.getElementById('_sesion_aviso')) return;

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

    {{-- Sesión única global --}}
    @auth
    <script>
        (function () {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

            // Generar un ID único para ESTA carga de página y registrarlo en BD
            const TAB_ID = Math.random().toString(36).substr(2, 20) + Date.now();

            fetch('/register-tab', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ tab_id: TAB_ID })
            }).then(function () {
                // Solo empezar a verificar DESPUÉS de registrarse exitosamente
                iniciarVerificacion();
            }).catch(function () {
                iniciarVerificacion();
            });

            function iniciarVerificacion() {
                setInterval(function () {
                    if (document.getElementById('_sesion_aviso')) return;
                    fetch('/check-tab?tab_id=' + TAB_ID, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    }).then(function (r) {
                        if (r.status === 401) mostrarAvisoSesion();
                    }).catch(function () {});
                }, 5000);
            }

            function mostrarAvisoSesion() {
                if (document.getElementById('_sesion_aviso')) return;
                const div = document.createElement('div');
                div.id = '_sesion_aviso';
                div.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99999;display:flex;align-items:center;justify-content:center;';
                div.innerHTML = `<div style="background:#1f2937;border:2px solid #ef4444;border-radius:12px;padding:32px 40px;text-align:center;max-width:400px;">
                    <div style="font-size:40px;margin-bottom:12px;">🔒</div>
                    <h2 style="color:#f87171;margin:0 0 10px;font-size:18px;">Sesión cerrada</h2>
                    <p style="color:#d1d5db;font-size:14px;margin:0 0 20px;">Tu sesión fue abierta en otro lugar.<br>Solo se permite una sesión activa a la vez.</p>
                    <button onclick="window.location.replace('/sesion-desactivada')"
                        style="background:#ef4444;color:white;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:700;cursor:pointer;">
                        Cerrar sesión
                    </button>
                </div>`;
                document.body.appendChild(div);
            }
        })();
    </script>
    @endauth

</body>
</html>
