<!DOCTYPE html>
<html lang="es" style="height: 100%; overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Punto de Venta</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Empresa del usuario que tiene la sesion activa en ESTE momento.
        // Usado para que el catalogo/cola de ventas offline guardados en
        // este navegador (IndexedDB, compartido entre sesiones) nunca se
        // muestren ni se sincronicen mezclados si en este mismo
        // dispositivo se usa el POS con otro negocio (ver pos-catalogo-
        // offline.js y pos-offline-queue.js).
        window.posEmpresaId = @json(auth()->check() ? auth()->user()->getEmpresaActualId() : null);

        // Dentro de Turion, "navigator.onLine" refleja si HAY INTERNET REAL
        // en la maquina -- no si el servidor local de Turion (127.0.0.1) esta
        // disponible, que SIEMPRE lo esta. Todo el modo "sin conexion" del
        // navegador (banner, carrito offline en JS puro, cola de sincronizacion
        // del navegador) fue pensado para cuando el POS SOLO vivia en el
        // droplet -- ahora Turion tiene su propia base de datos local real, asi
        // que ese modo degradado ya no debe activarse nunca aqui: el carrito
        // debe comportarse exactamente igual que en linea (ver
        // App\Http\Middleware\EnsureTerminalEmparejada / DB::getDriverName()).
        window.esTurion = @json(\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite');
        if (window.esTurion) {
            Object.defineProperty(window.navigator, 'onLine', {
                configurable: true,
                get: () => true,
            });
        }
    </script>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pos-catalogo-offline.js', 'resources/js/pos-offline-queue.js'])
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('css/pos-pro.css') }}?v={{ filemtime(public_path('css/pos-pro.css')) }}">
</head>

<body style="height: 100vh; margin: 0; padding: 0; overflow: hidden; background: #f3f4f6; color: #111827;">

    {{-- Titulo punto de venta --}}
    <div class="pos-app-header" style="position: relative; width: 100%; height: 60px; background: #2563eb; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 16px; border-bottom: 2px solid #1d4ed8; flex-shrink: 0;">
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
                    // ?modo=normal (boton "Punto de Venta" en /eleccion) fuerza el
                    // POS base sin ningun boton de taller/hotel, sin importar la
                    // configuracion de la empresa -- para cuando el negocio usa
                    // taller/hotel pero en ese momento se quiere el POS de tienda
                    // normal, tal cual.
                    $modoNormal = request()->get('modo') === 'normal';
                    $cfgEmpresa = \App\Models\ConfiguracionEmpresa::where('empresa_id', $u->getEmpresaActualId())->first();
                    $usaTallerLayout = ! $modoNormal && (bool) ($cfgEmpresa?->usa_taller ?? false) && $u->hasAnyRole(['taller', 'admin_empresa']);
                    $usaMesasLayout  = (bool) ($cfgEmpresa?->usa_mesas ?? false);
                    $usaHotelLayout  = ! $modoNormal && (bool) ($cfgEmpresa?->usa_hotel ?? false);
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
        <h1 class="pos-header-empresa" style="color: white; font-size: 24px; font-weight: bold; margin: 0; text-align: center; flex:0 1 auto; min-width:0; max-width:40%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            {{ $nombreEmpresa }}
        </h1>
        {{-- Derecha: nav de mesa (si aplica) + acciones (Administracion / offline / cerrar sesión) --}}
        <div style="flex:1 1 0; display:flex; align-items:center; justify-content:flex-end; gap:8px; min-width:max-content;">
            @hasSection('mesa-nav')
                @yield('mesa-nav')
            @endif

            <div class="pos-top-actions" x-data="{ menuOpen: false }" style="position: relative; z-index: 50; display:flex; align-items:center; gap:8px;">

              <button type="button" class="pos-mobile-menu-button" @click="menuOpen = !menuOpen" aria-label="Menu POS">
              </button>

              @if(
    request()->routeIs('pos') &&
    auth()->user()->hasAnyRole(['digitador', 'admin_empresa'])
)

<div class="pos-actions-list flex gap-2" :class="{ 'is-open': menuOpen }">

    @unless(\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite')
    <a href="/admin"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm rounded shadow inline-block">
        Administracion
    </a>
    @endunless

    <button type="button" id="pos-pendientes-badge"
        class="bg-indigo-700 hover:bg-indigo-800 text-white px-4 py-2 text-sm rounded shadow"
        style="display:none;">
    </button>

    <form method="POST" action="{{ route('cerrar.sesion') }}">
        @csrf

        <button type="submit"
            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded shadow">
            Cerrar sesion
        </button>
    </form>

</div>

@else

<div class="pos-actions-list flex gap-2" :class="{ 'is-open': menuOpen }">
<button type="button" id="pos-pendientes-badge"
    class="bg-indigo-700 hover:bg-indigo-800 text-white px-4 py-2 text-sm rounded shadow"
    style="display:none;">
</button>
<form method="POST" action="{{ route('cerrar.sesion') }}">
    @csrf
    <button type="submit"
        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded shadow">
        Cerrar sesion
    </button>
</form>
</div>

@endif

              @livewire('turion-sync-bar')
            </div>
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

    {{-- Impresion de tickets/facturas: Tauri (WebView2) bloquea window.open()
         y siempre devuelve null, asi que la impresion se hace con un iframe
         invisible en vez de una ventana emergente -- funciona igual en
         navegador normal y dentro de la app de escritorio. Cuando el
         documento que carga el iframe llama a window.print() (ver los
         .../imprimir.blade.php de facturas/prefactura/tickets), los
         navegadores basados en Chromium imprimen ese iframe especificamente,
         no toda la pagina. --}}
    <script>
        function posObtenerIframeImpresion() {
            let iframe = document.getElementById('pos-print-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'pos-print-iframe';
                iframe.style.cssText = 'position:fixed; left:-9999px; top:0; width:1px; height:1px; border:0;';
                document.body.appendChild(iframe);
            }
            return iframe;
        }

        window.posImprimirURL = function (url) {
            const iframe = posObtenerIframeImpresion();
            iframe.src = 'about:blank';
            setTimeout(() => { iframe.src = url; }, 0);
        };

        window.posImprimirHTML = function (html) {
            const iframe = posObtenerIframeImpresion();
            iframe.srcdoc = html;
        };
    </script>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('imprimir-cierre-caja', (event) => {
                const cajaId = event?.cajaId ?? event?.[0] ?? event;

                if (cajaId) {
                    window.posImprimirURL('/ticket-cierre-caja/' + cajaId);
                }
            });

            Livewire.on('open-print', (event) => {
                const url = event?.url ?? event?.[0]?.url ?? event;

                if (url) {
                    window.posImprimirURL(url);
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

            // Ancla el aviso justo debajo del header (y de las pestañas
            // Productos/Carrito en movil, si estan visibles) en vez de un
            // top fijo: el alto del header cambia segun el ancho de
            // pantalla y el aviso no debe tapar nada que se pueda tocar.
            function posicionarBannerOffline() {
                if (!banner) return;
                const header = document.querySelector('.pos-app-header');
                const tabs = document.querySelector('.pos-mobile-tabs');
                let bottom = 72;
                if (header) {
                    bottom = header.getBoundingClientRect().bottom;
                    if (tabs && getComputedStyle(tabs).display !== 'none') {
                        bottom = Math.max(bottom, tabs.getBoundingClientRect().bottom);
                    }
                }
                banner.style.top = (bottom + 8) + 'px';
            }

            function mostrarBannerOffline() {
                if (banner) return;
                banner = document.createElement('div');
                banner.id = 'pos-offline-banner';
                banner.textContent = '📴 Sin conexión — usando datos guardados localmente';
                banner.style.cssText = 'position:fixed;top:72px;right:14px;z-index:999999;background:#78350f;color:#fef3c7;padding:8px 18px;border-radius:999px;font-size:12px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.3);pointer-events:none;max-width:min(90vw,320px);text-align:center;';
                document.body.appendChild(banner);
                posicionarBannerOffline();
            }

            function ocultarBannerOffline() {
                if (!banner) return;
                banner.remove();
                banner = null;
            }

            window.addEventListener('resize', posicionarBannerOffline);

            window.addEventListener('offline', mostrarBannerOffline);
            window.addEventListener('online', () => {
                ocultarBannerOffline();
                window.PosCatalogoOffline?.sincronizarCatalogo();
                window.PosOfflineQueue?.procesarCola();
            });

            if (navigator.onLine === false) mostrarBannerOffline();

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js').catch(() => {});
                });
            }
        })();
    </script>

    {{-- Indicador de ventas pendientes de sincronizar (guardadas mientras
         no habia conexion) --}}
    <script>
        (function () {
            let badge = null;

            function crearBadge() {
                badge = document.getElementById('pos-pendientes-badge');
                if (!badge) return;
                badge.addEventListener('click', async () => {
                    if (navigator.onLine === false) {
                        window.Swal?.fire({
                            icon: 'info',
                            title: 'Sin conexion',
                            text: 'Se van a enviar solas apenas vuelva el internet. No se puede sincronizar mientras no haya conexion.',
                            confirmButtonText: 'Entendido',
                        });
                        return;
                    }

                    badge.disabled = true;
                    const textoOriginal = badge.textContent;
                    badge.textContent = '⏳ Sincronizando...';

                    await window.PosOfflineQueue?.reintentarConflictos();
                    await window.PosOfflineQueue?.procesarCola();

                    badge.disabled = false;
                    badge.textContent = textoOriginal;
                });
            }

            async function actualizarBadge() {
                if (!window.PosOfflineQueue) return;
                if (!badge) crearBadge();
                if (!badge) return;

                const total = await window.PosOfflineQueue.contarPendientes();

                if (total > 0) {
                    badge.textContent = '🕓 ' + total + ' pendiente' + (total === 1 ? '' : 's') + ' de sincronizar';
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }

            window.addEventListener('pos-cola-cambio', actualizarBadge);

            window.addEventListener('pos-operacion-sincronizada', () => {
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'success', title: 'Sincronizado', timer: 1500, showConfirmButton: false,
                    });
                }
            });

            window.addEventListener('pos-operacion-conflicto', (event) => {
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Quedo pendiente de revisar',
                        text: event.detail?.error || 'No se pudo sincronizar una venta.',
                        confirmButtonText: 'Entendido',
                    });
                }
            });

            document.addEventListener('DOMContentLoaded', () => {
                actualizarBadge();
                if (navigator.onLine !== false) window.PosOfflineQueue?.procesarCola();
            });
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
