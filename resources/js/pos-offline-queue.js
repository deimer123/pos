/**
 * Cola de ventas simples guardadas sin conexion. Cada venta que no se
 * pudo mandar al servidor por falta de internet se guarda aqui y se
 * reintenta cuando vuelve la conexion (o al pulsar "Sincronizar
 * pendientes"). Solo cubre venta simple -- no mesas, taller ni hotel,
 * que siempre necesitan internet.
 */

import { abrirDB, STORE_OPERACIONES } from './pos-offline-db.js';

const ENDPOINT_VENTA = '/pos/sync/venta';

let procesandoCola = false;

function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    // Respaldo simple para navegadores sin crypto.randomUUID
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

function avisarCambio() {
    window.dispatchEvent(new CustomEvent('pos-cola-cambio'));
}

async function conTransaccion(modo, fn) {
    const db = await abrirDB();
    const tx = db.transaction(STORE_OPERACIONES, modo);
    const store = tx.objectStore(STORE_OPERACIONES);
    const resultado = await fn(store);

    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve(resultado);
        tx.onerror = () => reject(tx.error);
    });
}

function pedirTodo(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

/**
 * Encola una venta para sincronizar despues. El carrito y el medio de
 * pago se guardan tal cual -- el numero de factura real y el "salida" no
 * fiscal los decide el servidor al procesar la cola, en el orden en que
 * se crearon las ventas (ver PosSyncController::venta()).
 */
export async function agregarOperacion({ payload }) {
    const operacion = {
        uuid: uuid(),
        payload,
        estado: 'pendiente',
        error: null,
        creado_en: Date.now(),
        // Empresa de la sesion que la creo (window.posEmpresaId, fijado en
        // pos.blade.php). IndexedDB es una sola base compartida por el
        // navegador: si despues se usa este mismo dispositivo con OTRO
        // negocio, esta venta NO se debe sincronizar ni contar ahi -- se
        // queda guardada tal cual hasta que la empresa que la creo vuelva
        // a tener sesion activa.
        empresa_id: window.posEmpresaId ?? null,
    };

    await conTransaccion('readwrite', (store) => {
        store.put(operacion);
        return null;
    });

    avisarCambio();
    return operacion.uuid;
}

export async function listarOperaciones() {
    return conTransaccion('readonly', (store) => pedirTodo(store));
}

/**
 * true si la operacion es de la empresa que tiene sesion activa ahora
 * mismo. Una venta sin empresa_id (encolada antes de este cambio) NO se
 * da por buena cuando se sabe cual es la empresa actual: es preferible
 * dejarla sin sincronizar (requiere revisarla a mano) a arriesgarse a
 * facturarla a nombre de un negocio que no es el suyo.
 */
function esDeEmpresaActual(op) {
    if (window.posEmpresaId === undefined || window.posEmpresaId === null) return true;
    if (op.empresa_id === undefined || op.empresa_id === null) return false;
    return Number(op.empresa_id) === Number(window.posEmpresaId);
}

export async function contarPendientes() {
    const todas = await listarOperaciones();
    return todas.filter((op) => esDeEmpresaActual(op) && (op.estado === 'pendiente' || op.estado === 'conflicto')).length;
}

/**
 * Vuelve a poner en "pendiente" las ventas en conflicto para que la
 * proxima corrida de procesarCola() las intente de nuevo. El endpoint de
 * sincronizacion es idempotente por uuid, asi que reintentar no duplica
 * nada aunque la venta ya hubiera llegado al servidor.
 */
export async function reintentarConflictos() {
    const todas = await listarOperaciones();

    for (const op of todas) {
        if (esDeEmpresaActual(op) && op.estado === 'conflicto') {
            await actualizarOperacion(op.uuid, { estado: 'pendiente', error: null });
        }
    }
}

async function actualizarOperacion(uuidOp, cambios) {
    await conTransaccion('readwrite', async (store) => {
        const existente = await new Promise((resolve, reject) => {
            const req = store.get(uuidOp);
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

        if (existente) {
            store.put({ ...existente, ...cambios });
        }

        return null;
    });

    avisarCambio();
}

export async function eliminarOperacion(uuidOp) {
    await conTransaccion('readwrite', (store) => {
        store.delete(uuidOp);
        return null;
    });

    avisarCambio();
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function enviarOperacion(operacion) {
    const response = await fetch(ENDPOINT_VENTA, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ uuid: operacion.uuid, ...operacion.payload }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const mensaje = data?.message || 'Error al sincronizar (' + response.status + ')';
        throw new Error(mensaje);
    }

    return data;
}

/**
 * Procesa la cola en orden de creacion (mismo orden en que se hicieron
 * las ventas), una por una, para que el servidor les asigne numero de
 * factura consecutivo real. Una venta en conflicto no bloquea a las
 * demas -- sigue con la siguiente y esa queda para revisar a mano.
 */
export async function procesarCola() {
    if (procesandoCola) return;
    procesandoCola = true;

    try {
        const todas = await listarOperaciones();

        const operaciones = todas
            .filter((op) => esDeEmpresaActual(op) && op.estado === 'pendiente')
            .sort((a, b) => a.creado_en - b.creado_en);

        for (const operacion of operaciones) {
            await actualizarOperacion(operacion.uuid, { estado: 'sincronizando' });

            try {
                const resultado = await enviarOperacion(operacion);

                window.dispatchEvent(new CustomEvent('pos-operacion-sincronizada', {
                    detail: { operacion, resultado },
                }));

                await eliminarOperacion(operacion.uuid);
            } catch (e) {
                const mensaje = e.message || 'Error desconocido al sincronizar.';

                await actualizarOperacion(operacion.uuid, {
                    estado: 'conflicto',
                    error: mensaje,
                });

                window.dispatchEvent(new CustomEvent('pos-operacion-conflicto', {
                    detail: { operacion, error: mensaje },
                }));
            }
        }
    } finally {
        procesandoCola = false;
        avisarCambio();
    }
}

window.PosOfflineQueue = {
    agregarOperacion,
    listarOperaciones,
    contarPendientes,
    eliminarOperacion,
    procesarCola,
    reintentarConflictos,
};
