/**
 * Service worker minimo para el POS: cachea en caliente los assets
 * estaticos del build (Vite) y la shell HTML de /pos, para que la
 * pagina cargue aunque no haya conexion (una vez visitada antes). No
 * intercepta llamadas a Livewire (POST) ni al catalogo JSON: esas las
 * maneja la propia app (ver pos-catalogo-offline.js).
 */

const CACHE_NAME = 'pos-shell-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((nombres) => Promise.all(
            nombres
                .filter((nombre) => nombre !== CACHE_NAME)
                .map((nombre) => caches.delete(nombre))
        )).then(() => self.clients.claim())
    );
});

function esAssetEstatico(url) {
    return url.pathname.startsWith('/build/')
        || /\.(css|js|png|jpg|jpeg|svg|webp|woff2?|ico)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Shell HTML de /pos: network-first, con respaldo en cache si no hay conexion.
    // ignoreSearch=true porque la misma pantalla se visita con distintos
    // query strings (?modo=normal, ?modo=mesas...) y sin conexion no
    // importa cual haya quedado cacheada, sirve cualquiera como shell.
    if (request.mode === 'navigate' && url.pathname.startsWith('/pos')) {
        event.respondWith(
            fetch(request)
                .then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copia));
                    return respuesta;
                })
                .catch(() => caches.match(request, { ignoreSearch: true }))
        );
        return;
    }

    // Assets estaticos del build: cache-first.
    if (esAssetEstatico(url)) {
        event.respondWith(
            caches.match(request).then((cacheada) => {
                if (cacheada) return cacheada;

                return fetch(request).then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copia));
                    return respuesta;
                });
            })
        );
    }
});

/**
 * Notificaciones push: cuando cocina marca un pedido como listo, el
 * backend envia un push a los meseros/cajeros suscritos. Esto dispara
 * el evento 'push' aqui aunque la pestaña este cerrada o la pantalla
 * apagada (el sistema operativo despierta el service worker).
 */
self.addEventListener('push', (event) => {
    let datos = {};
    try {
        datos = event.data ? event.data.json() : {};
    } catch (e) {
        datos = { body: event.data ? event.data.text() : '' };
    }

    const titulo = datos.title || 'Pedido listo';
    const opciones = {
        body: datos.body || '',
        icon: '/favicon-192x192.png',
        badge: '/favicon-192x192.png',
        tag: datos.tag || 'pedido-listo',
        requireInteraction: true,
        vibrate: [200, 100, 200],
        data: { url: datos.url || '/pos?modo=mesas' },
    };

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(titulo, opciones),
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientes) => {
                clientes.forEach((cliente) => cliente.postMessage({ type: 'pedido-listo-sonido', ...datos }));
            }),
        ])
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/pos?modo=mesas';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientesAbiertos) => {
            const existente = clientesAbiertos.find((c) => c.url.includes('/pos'));
            if (existente) return existente.focus();
            return self.clients.openWindow(url);
        })
    );
});
