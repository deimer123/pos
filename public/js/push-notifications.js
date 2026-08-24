/**
 * Activa notificaciones push reales (llegan aunque el navegador este
 * cerrado o la pantalla apagada) para avisar a meseros/cajeros cuando
 * cocina marca un pedido como listo. Tambien reproduce un sonido en
 * cualquier pestaña abierta del POS cuando llega el push (ver el
 * postMessage en service-worker.js).
 */
(function () {
    function base64UrlAUint8Array(base64Url) {
        const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
        const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const outputArray = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) {
            outputArray[i] = raw.charCodeAt(i);
        }
        return outputArray;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function reproducirSonidoAlerta() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            const ctx = new Ctx();
            const ahora = ctx.currentTime;

            [0, 0.35].forEach((retraso) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.0001, ahora + retraso);
                gain.gain.exponentialRampToValueAtTime(0.5, ahora + retraso + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ahora + retraso + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ahora + retraso);
                osc.stop(ahora + retraso + 0.32);
            });

            setTimeout(() => ctx.close(), 1000);
        } catch (e) {
            // Ignorar: navegadores que bloquean audio sin interaccion previa.
        }
    }

    async function suscribir() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return { ok: false, motivo: 'no_soportado' };
        }

        if (!window.vapidPublicKey) {
            return { ok: false, motivo: 'sin_llave' };
        }

        const permiso = await Notification.requestPermission();
        if (permiso !== 'granted') {
            return { ok: false, motivo: 'permiso_denegado' };
        }

        const registro = await navigator.serviceWorker.ready;

        let suscripcion = await registro.pushManager.getSubscription();
        if (!suscripcion) {
            suscripcion = await registro.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlAUint8Array(window.vapidPublicKey),
            });
        }

        await fetch('/push/subscribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(suscripcion.toJSON()),
        });

        return { ok: true };
    }

    async function estaActivo() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
        if (Notification.permission !== 'granted') return false;

        const registro = await navigator.serviceWorker.ready;
        const suscripcion = await registro.pushManager.getSubscription();
        return !!suscripcion;
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'pedido-listo-sonido') {
                reproducirSonidoAlerta();
            }
        });
    }

    window.PosPushNotifications = { suscribir, estaActivo };
})();
