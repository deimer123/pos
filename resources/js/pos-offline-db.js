/**
 * Apertura compartida de la base IndexedDB "pos_offline". Centralizada
 * aqui para que el catalogo de productos (Fase 1) y la cola de
 * operaciones offline (Fase 2) usen siempre la MISMA version de la base
 * y no se pisen entre si al crear/actualizar los object stores.
 */

export const DB_NAME = 'pos_offline';
export const DB_VERSION = 3;
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
