/**
 * Clientes del POS guardados localmente (IndexedDB), para poder buscar
 * un cliente existente o crear uno nuevo sin conexion. Mismo patron que
 * pos-catalogo-offline.js (Fase 1), pero para clientes en vez de
 * productos.
 */

import { abrirDB, STORE_CLIENTES } from './pos-offline-db.js';

const SYNC_AT_KEY = 'pos_clientes_sincronizado_en';
const EMPRESA_KEY = 'pos_clientes_empresa_id';
const SYNC_STALE_MS = 30 * 60 * 1000;

let clientesEnMemoria = [];

// Ver misma logica/comentario en pos-catalogo-offline.js.
function esCacheDeOtraEmpresa() {
    if (window.posEmpresaId === undefined || window.posEmpresaId === null) return false;
    const guardada = localStorage.getItem(EMPRESA_KEY);
    if (guardada === null) return true;
    return Number(guardada) !== Number(window.posEmpresaId);
}

async function leerTodoDeIndexedDB() {
    const db = await abrirDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_CLIENTES, 'readonly');
        const request = tx.objectStore(STORE_CLIENTES).getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

async function reemplazarEnIndexedDB(clientes) {
    const db = await abrirDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_CLIENTES, 'readwrite');
        const store = tx.objectStore(STORE_CLIENTES);
        store.clear();
        clientes.forEach((c) => store.put(c));
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function sincronizarClientes() {
    try {
        const response = await fetch('/pos/clientes.json', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) return false;

        const data = await response.json();
        const clientes = data.clientes || [];

        await reemplazarEnIndexedDB(clientes);
        clientesEnMemoria = clientes;
        localStorage.setItem(SYNC_AT_KEY, String(Date.now()));

        if (data.empresa_id !== undefined && data.empresa_id !== null) {
            localStorage.setItem(EMPRESA_KEY, String(data.empresa_id));
        }

        return true;
    } catch (e) {
        return false;
    }
}

export async function cargarClientesLocal() {
    if (esCacheDeOtraEmpresa()) {
        clientesEnMemoria = [];
        sincronizarClientes();
        return clientesEnMemoria;
    }

    try {
        clientesEnMemoria = await leerTodoDeIndexedDB();
    } catch (e) {
        clientesEnMemoria = [];
    }

    const sincronizadoEn = Number(localStorage.getItem(SYNC_AT_KEY) || 0);
    if (clientesEnMemoria.length === 0 || (Date.now() - sincronizadoEn) > SYNC_STALE_MS) {
        sincronizarClientes();
    }

    return clientesEnMemoria;
}

function quitarAcentos(texto) {
    return (texto || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

export function buscarClientesLocal(query) {
    const q = quitarAcentos(query).trim();
    if (!q) return clientesEnMemoria.slice(0, 30);

    return clientesEnMemoria
        .filter((c) => quitarAcentos(c.nombre).includes(q) || quitarAcentos(c.identificacion).includes(q))
        .slice(0, 30);
}

export function getClientesCache() {
    return clientesEnMemoria;
}

window.PosClientesOffline = {
    sincronizarClientes,
    cargarClientesLocal,
    buscarClientesLocal,
    getClientesCache,
};
