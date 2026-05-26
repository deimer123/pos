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