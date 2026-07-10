<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Cocina — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body { margin:0; background:#111827; font-family:system-ui,sans-serif; }
        .cocina-topbar { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:10px 16px; background:#111827; }
    </style>
</head>
<body>
    {{-- Barra superior: usuario logueado + botón cerrar sesión --}}
    <div class="cocina-topbar">
        @auth
        <span style="background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); border-radius:20px; padding:4px 12px; font-size:12px; color:#d1d5db; white-space:nowrap;">
            👤 {{ auth()->user()->name }} · Cocina
        </span>
        @endauth
        <form method="POST" action="{{ route('cocina.logout') }}">
            @csrf
            <button type="submit"
                style="background:#dc2626; color:white; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap;">
                🔒 Cerrar sesión
            </button>
        </form>
    </div>

    {{ $slot }}

    @livewireScripts

    <script>
        // Inactividad máxima cocina: 2 horas
        let _cocinaTimeout;
        let _cocinaCerrada = false;

        function _cocinaCerrar() {
            if (_cocinaCerrada) return;
            _cocinaCerrada = true;
            clearTimeout(_cocinaTimeout);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch('{{ route('cocina.logout') }}', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            }).finally(() => {
                window.location.replace('/admin/login?inactive=1');
            });
        }

        function _cocinaReiniciar() {
            if (_cocinaCerrada) return;
            clearTimeout(_cocinaTimeout);
            _cocinaTimeout = setTimeout(_cocinaCerrar, 2 * 60 * 60 * 1000);
        }

        ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(e => {
            document.addEventListener(e, _cocinaReiniciar, { passive: true });
        });

        _cocinaReiniciar();
    </script>

    @auth
    <script>
        (function () {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const TAB_ID = Math.random().toString(36).substr(2, 20) + Date.now();

            fetch('/register-tab', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ tab_id: TAB_ID })
            }).then(iniciarVerificacion).catch(iniciarVerificacion);

            function iniciarVerificacion() {
                setInterval(function () {
                    if (document.getElementById('_sesion_aviso')) return;
                    fetch('/check-tab?tab_id=' + TAB_ID, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    }).then(function (r) { if (r.status === 401) mostrarAviso(); })
                    .catch(function () {});
                }, 5000);
            }

            function mostrarAviso() {
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
