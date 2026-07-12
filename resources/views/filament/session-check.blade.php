<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const loginPath = '/admin/login';
    const loginUrl = loginPath + '?inactive=1';

    function mostrarMensajeEnLogin() {
        const params = new URLSearchParams(window.location.search);

        if (!window.location.pathname.includes(loginPath) || params.get('inactive') !== '1') {
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Sesion cerrada por inactividad',
            text: 'Por seguridad, debe iniciar sesion nuevamente.',
            confirmButtonText: 'Aceptar',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.history.replaceState({}, document.title, loginPath);
        });
    }

    mostrarMensajeEnLogin();

    if (window.location.pathname.includes(loginPath)) {
        return;
    }

    let tiempoMaximo = 30 * 60 * 1000;
    let ultimaActividad = Date.now();
    let sesionCerrada = false;
    let confirmOriginal = window.confirm.bind(window);

    function cerrarSesionPorInactividad() {
        if (sesionCerrada) return;

        sesionCerrada = true;

        const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

        fetch('/cerrar-sesion', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        }).finally(() => {
            window.location.replace(loginUrl);
        });
    }

    window.confirm = function (mensaje) {
        let texto = String(mensaje || '');

        if (
            texto.includes('This page has expired') ||
            texto.includes('Would you like to refresh the page') ||
            texto.includes('Page Expired') ||
            texto.includes('419')
        ) {
            cerrarSesionPorInactividad();
            return false;
        }

        return confirmOriginal(mensaje);
    };

    window.addEventListener('unhandledrejection', function (event) {
        let texto = String(event.reason?.message || event.reason || '');

        if (texto.includes('419') || texto.includes('Page Expired') || texto.includes('CSRF')) {
            event.preventDefault();
            cerrarSesionPorInactividad();
        }
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

    function registrarActividad() {
        if (sesionCerrada) return;
        ultimaActividad = Date.now();
    }

    function verificar() {
        if (sesionCerrada) return;

        if ((Date.now() - ultimaActividad) > tiempoMaximo) {
            cerrarSesionPorInactividad();
        }
    }

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(e => {
        document.addEventListener(e, registrarActividad, { passive: true });
    });

    setInterval(verificar, 1000);
})();
</script>

{{-- Sesión única global (misma pestaña que se muestra en el POS): si el
     usuario entra desde otra pestaña o dispositivo con la misma cuenta,
     esta pestaña se cierra sola, sin esperar a que haga clic en algo. --}}
@auth
<script>
(function () {
    if (window.location.pathname.includes('/admin/login')) return;

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