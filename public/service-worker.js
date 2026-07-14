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
    if (request.mode === 'navigate' && url.pathname.startsWith('/pos')) {
        event.respondWith(
            fetch(request)
                .then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copia));
                    return respuesta;
                })
                .catch(() => caches.match(request))
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
