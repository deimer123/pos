<!DOCTYPE html>
<html lang="es" style="height: 100%; overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Punto de Venta</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pos-catalogo-offline.js'])
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('css/pos-pro.css') }}?v={{ filemtime(public_path('css/pos-pro.css')) }}">
</head>

<body style="height: 100vh; margin: 0; padding: 0; overflow: hidden; background: #f3f4f6; color: #111827;">

    {{-- Boton de logout --}}
    <div class="pos-top-actions" x-data="{ menuOpen: false }" style="position: fixed; top: 10px; right: 20px; z-index: 50; display:flex; align-items:center; gap:8px;">

      <button type="button" class="pos-mobile-menu-button" @click="menuOpen = !menuOpen" aria-label="Menu POS" style="display:none;">
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
    <div class="pos-app-header" style="width: 100%; height: 60px; background: #2563eb; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; border-bottom: 2px solid #1d4ed8; flex-shrink: 0;">
        {{-- Izquierda: botón ← Mesas (solo en vista mesa) o usuario logueado --}}
        <div class="pos-header-left" style="flex:1; display:flex; align-items:center; justify-content:flex-start; gap:8px; min-width:0;">
            @hasSection('header-left')
                @yield('header-left')
            @else
                @auth
                @php
                    $u = auth()->user();
                    $roleLabels = ['admin_empresa'=>'Admin','cajero'=>'Cajero','vendedor'=>'Vendedor','mesero'=>'Mesero','digitador'=>'Digitador','taller'=>'Taller','cocina'=>'Cocina','recepcion'=>'Recepción'];
                    $rolActual = collect($roleLabels)->first(fn($l,$r) => $u->hasRole($r));
                @endphp
                <span style="background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); border-radius:20px; padding:3px 12px; font-size:12px; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;">
                    👤 {{ $u->name }}@if($rolActual) · {{ $rolActual }}@endif
                </span>
                @php
                    $cfgEmpresa = \App\Models\ConfiguracionEmpresa::where('empresa_id', $u->getEmpresaActualId())->first();
                    $usaTallerLayout = (bool) ($cfgEmpresa?->usa_taller ?? false) && $u->hasAnyRole(['taller', 'admin_empresa']);
                    $usaMesasLayout  = (bool) ($cfgEmpresa?->usa_mesas ?? false);
                    $usaHotelLayout  = (bool) ($cfgEmpresa?->usa_hotel ?? false);
                @endphp
                @if($usaTallerLayout)
                    @if($usaMesasLayout)
                        {{-- En modo mesas: el toggle está dentro de panel-mesas --}}
                    @else
                        {{-- En modo tienda: botón directo al panel taller --}}
                        @php
                            $ordenesActivasTaller = \App\Models\TallerOrden::where('empresa_id', $u->getEmpresaActualId())
                                ->whereIn('estado', ['pendiente','en_proceso','listo'])->count();
                        @endphp
                        @if(!request()->routeIs('taller'))
                        <a href="{{ route('taller') }}" id="btn-ir-taller"
                           onclick="irAlTallerConGuardado(event, '{{ route('taller') }}')"
                           style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                               background:rgba(255,255,255,.2); color:white; display:flex; align-items:center; gap:5px; text-decoration:none;">
                            🔧 Taller
                            @if($ordenesActivasTaller > 0)
                                <span style="background:#ef4444; border-radius:99px; padding:1px 6px; font-size:10px;">{{ $ordenesActivasTaller }}</span>
                            @endif
                        </a>
                        @else
                        <a href="{{ route('pos') }}"
                           style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                               background:rgba(255,255,255,.2); color:white; text-decoration:none;">
                            🛒 POS
                        </a>
                        @endif
                    @endif
                @endif
                @if($usaHotelLayout && !$usaMesasLayout)
                    @if(!request()->routeIs('hotel'))
                    <a href="{{ route('hotel') }}" id="btn-ir-hotel"
                       onclick="irAlHotelConGuardado(event, '{{ route('hotel') }}')"
                       style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                           background:rgba(255,255,255,.2); color:white; display:flex; align-items:center; gap:5px; text-decoration:none;">
                        🏨 Hotel
                    </a>
                    @else
                    <a href="{{ route('pos') }}"
                       style="border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
                           background:rgba(255,255,255,.2); color:white; text-decoration:none;">
                        🛒 POS
                    </a>
                    @endif
                @endif
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
    <script>
    function irAlTallerConGuardado(event, urlTaller) {
        // Buscar el componente carrito-venta
        const carritoEl = document.querySelector('[wire\\:id]');
        if (!carritoEl) { window.location.href = urlTaller; return; }

        const wire = Livewire.find(carritoEl.getAttribute('wire:id'));
        if (!wire) { window.location.href = urlTaller; return; }

        const tallerOrdenId = wire.get('tallerOrdenId');
        if (!tallerOrdenId) {
            // No hay orden activa, navegar directo
            window.location.href = urlTaller;
            return;
        }

        // Hay orden activa: prevenir navegación y guardar primero
        event.preventDefault();
        Swal.fire({
            title: '¿Ir al lobby?',
            text: 'Los productos del carrito se guardarán en la orden antes de salir.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '💾 Guardar y salir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#6b7280',
        }).then(r => {
            if (r.isConfirmed) {
                wire.call('salirALobbyTaller');
            }
        });
    }

    function irAlHotelConGuardado(event, urlHotel) {
        const carritoEl = document.querySelector('[wire\\:id]');
        if (!carritoEl) { window.location.href = urlHotel; return; }

        const wire = Livewire.find(carritoEl.getAttribute('wire:id'));
        if (!wire) { window.location.href = urlHotel; return; }

        const hotelReservaId = wire.get('hotelReservaId');
        if (!hotelReservaId) {
            window.location.href = urlHotel;
            return;
        }

        event.preventDefault();
        Swal.fire({
            title: '¿Ir al lobby?',
            text: 'Los productos del carrito se guardarán en la reserva antes de salir.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '💾 Guardar y salir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6b7280',
        }).then(r => {
            if (r.isConfirmed) {
                wire.call('salirALobbyHotel');
            }
        });
    }
    </script>
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
                    // Si no hay conexion, un 401/419 no es una sesion vencida
                    // de verdad: es la red cayendose. No forzar el logout/reload
                    // en ese caso, se reintenta solo cuando vuelva la señal.
                    if (navigator.onLine === false) {
                        preventDefault();
                        return;
                    }

                    if (status === 401 || status === 419) {
                        preventDefault();
                        cerrarSesionPorInactividad();
                    }
                });
            });
        });

        reiniciarTemporizador();
    </script>

    {{-- Aviso de conexion + service worker (para que el POS no se rompa/pida
         recargar cuando se cae internet un momento) --}}
    <script>
        (function () {
            let banner = null;

            function mostrarBannerOffline() {
                if (banner) return;
                banner = document.createElement('div');
                banner.id = 'pos-offline-banner';
                banner.textContent = '📴 Sin conexión — usando datos guardados localmente';
                banner.style.cssText = 'position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:999999;background:#78350f;color:#fef3c7;padding:8px 18px;border-radius:999px;font-size:12px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.3);';
                document.body.appendChild(banner);
            }

            function ocultarBannerOffline() {
                if (!banner) return;
                banner.remove();
                banner = null;
            }

            window.addEventListener('offline', mostrarBannerOffline);
            window.addEventListener('online', () => {
                ocultarBannerOffline();
                window.PosCatalogoOffline?.sincronizarCatalogo();
            });

            if (navigator.onLine === false) mostrarBannerOffline();

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js').catch(() => {});
                });
            }
        })();
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
