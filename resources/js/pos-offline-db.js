/**
 * Apertura compartida de la base IndexedDB "pos_offline". Centralizada
 * aqui para que el catalogo de productos (Fase 1), clientes, auth offline
 * y la cola de operaciones offline (Fase 2/3) usen siempre la MISMA
 * version de la base y no se pisen entre si al crear/actualizar los
 * object stores.
 *
 * Ojo: este numero solo puede SUBIR, nunca bajar. Si el navegador de
 * alguien ya abrio esta base ("pos_offline") en una version mas alta
 * (distintas ramas de desarrollo llegaron a usar hasta la 10 antes de
 * centralizarse aqui), pedir una version menor rompe la apertura con un
 * VersionError que este codigo atrapa en silencio -- se queda pegado
 * sin ningun error visible en consola. Por eso arranca en 11, por encima
 * de cualquier version usada antes en cualquier rama.
 */
export const DB_NAME = 'pos_offline';
export const DB_VERSION = 11;
export const STORE_PRODUCTOS = 'productos';
export const STORE_OPERACIONES = 'operaciones';
export const STORE_CLIENTES = 'clientes';
export const STORE_AUTH_OFFLINE = 'auth_offline';

let dbPromise = null;

export function abrirDB() {
    if (dbPromise) return dbPromise;

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains(STORE_PRODUCTOS)) {
                db.createObjectStore(STORE_PRODUCTOS, { keyPath: 'id_producto' });
            }

            if (!db.objectStoreNames.contains(STORE_OPERACIONES)) {
                const store = db.createObjectStore(STORE_OPERACIONES, { keyPath: 'uuid' });
                store.createIndex('estado', 'estado');
                store.createIndex('creado_en', 'creado_en');
            }

            if (!db.objectStoreNames.contains(STORE_CLIENTES)) {
                db.createObjectStore(STORE_CLIENTES, { keyPath: 'id' });
            }

            if (!db.objectStoreNames.contains(STORE_AUTH_OFFLINE)) {
                db.createObjectStore(STORE_AUTH_OFFLINE, { keyPath: 'user_id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}
