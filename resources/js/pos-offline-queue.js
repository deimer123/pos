/**
 * Cola generica de operaciones offline (venta simple, items/facturar de
 * mesa, taller y hotel). Cada accion que no se pudo mandar al servidor
 * por falta de conexion se guarda aqui y se reintenta cuando vuelve el
 * internet (o al pulsar "Sincronizar pendientes").
 */

import { abrirDB, STORE_OPERACIONES } from './pos-offline-db.js';

// tipo -> ruta del endpoint de sincronizacion en el servidor
const ENDPOINTS = {
    venta: '/pos/sync/venta',
    cliente_crear: '/pos/sync/cliente-crear',
    mesa_item: '/pos/sync/mesa-item',
    mesa_facturar: '/pos/sync/mesa-facturar',
    taller_crear: '/pos/sync/taller-crear',
    taller_item: '/pos/sync/taller-item',
    taller_facturar: '/pos/sync/taller-facturar',
    hotel_crear: '/pos/sync/hotel-crear',
    hotel_item: '/pos/sync/hotel-item',
    hotel_facturar: '/pos/sync/hotel-facturar',
};

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
 * Encola una operacion para sincronizar despues. `dependeDe` es el uuid
 * de otra operacion que debe sincronizarse primero (ej. agregar un item
 * a una orden de taller creada offline depende de que esa creacion ya
 * tenga un id real).
 */
export async function agregarOperacion({ tipo, payload, dependeDe = null }) {
    const operacion = {
        uuid: uuid(),
        tipo,
        payload,
        depende_de: dependeDe,
        estado: 'pendiente',
        error: null,
        creado_en: Date.now(),
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

export async function contarPendientes() {
    const todas = await listarOperaciones();
    return todas.filter((op) => op.estado === 'pendiente' || op.estado === 'conflicto').length;
}

/**
 * Vuelve a poner en "pendiente" las operaciones en conflicto para que la
 * proxima corrida de procesarCola() las intente de nuevo. Pensado para
 * cuando el conflicto fue por algo transitorio (token de sesion vencido,
 * corte de red) y no por una regla de negocio real: los endpoints de
 * sincronizacion son idempotentes por uuid, asi que reintentar no
 * duplica nada aunque la operacion ya hubiera llegado al servidor.
 */
export async function reintentarConflictos() {
    const todas = await listarOperaciones();

    for (const op of todas) {
        if (op.estado === 'conflicto') {
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

/**
 * Sustituye referencias a ids temporales (uuid de otra operacion ya
 * encolada) por el id real ya asignado por el servidor, usando el mapa
 * de resultados acumulado durante esta corrida de sincronizacion.
 * Convencion: un campo "xxx_uuid_ref" con el uuid de la operacion de la
 * que depende se convierte en "xxx_id" con el id real.
 */
function resolverPayload(payload, resultadosPorUuid) {
    const resuelto = { ...payload };

    for (const [clave, valor] of Object.entries(payload)) {
        if (!clave.endsWith('_uuid_ref')) continue;

        const idReal = resultadosPorUuid.get(valor);
        if (idReal === undefined) return null; // aun no se puede resolver

        const claveFinal = clave.replace(/_uuid_ref$/, '_id');
        resuelto[claveFinal] = idReal;
        delete resuelto[clave];
    }

    return resuelto;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/**
 * Best-effort: si esto tambien falla (ej. se volvio a caer el internet
 * justo despues de detectar el conflicto), no pasa nada — el conflicto
 * ya quedo visible localmente igual, y se reintenta reportar la proxima
 * vez que se procese la cola.
 */
function reportarConflictoAlServidor(operacion, mensaje) {
    fetch('/pos/sync/reportar-conflicto', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ uuid: operacion.uuid, tipo: operacion.tipo, payload: operacion.payload, error: mensaje }),
    }).catch(() => {});
}

async function enviarOperacion(operacion, payloadResuelto) {
    const endpoint = ENDPOINTS[operacion.tipo];
    if (!endpoint) throw new Error('Tipo de operacion desconocido: ' + operacion.tipo);

    const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ uuid: operacion.uuid, ...payloadResuelto }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const mensaje = data?.message || 'Error al sincronizar (' + response.status + ')';
        throw new Error(mensaje);
    }

    return data;
}

/**
 * Procesa la cola en orden de creacion. Las operaciones cuya dependencia
 * todavia no se resolvio se dejan pendientes para el proximo intento.
 * Una operacion en conflicto no bloquea a las demas.
 *
 * Las operaciones completadas no se borran de inmediato: quedan como
 * "completada" con su id real, por si otra operacion depende de ellas y
 * todavia no le toco su turno (puede pasar en corridas distintas, ej. la
 * pagina se recargo entre crear un cliente offline y facturarle la venta).
 * Al final de cada corrida se limpian las que ya nadie necesita.
 */
export async function procesarCola() {
    if (procesandoCola) return;
    procesandoCola = true;

    try {
        const todas = await listarOperaciones();

        const resultadosPorUuid = new Map();
        for (const op of todas) {
            if (op.estado === 'completada') resultadosPorUuid.set(op.uuid, op.resultado_id ?? null);
        }

        const uuidsExistentes = new Set(todas.map((op) => op.uuid));

        const operaciones = todas
            .filter((op) => op.estado === 'pendiente')
            .sort((a, b) => a.creado_en - b.creado_en);

        for (const operacion of operaciones) {
            let payloadResuelto = operacion.payload;

            if (operacion.depende_de) {
                if (!resultadosPorUuid.has(operacion.depende_de)) {
                    if (uuidsExistentes.has(operacion.depende_de)) continue; // se reintenta en la siguiente corrida

                    // La operacion de la que dependia ya no existe en la cola
                    // ni quedo registrada como completada: quedo huerfana y
                    // nunca se va a poder resolver sola. Se marca como
                    // conflicto para que un admin la revise a mano.
                    const mensaje = 'No se pudo sincronizar: la operacion de la que dependia ya no esta disponible.';

                    await actualizarOperacion(operacion.uuid, { estado: 'conflicto', error: mensaje });
                    reportarConflictoAlServidor(operacion, mensaje);

                    window.dispatchEvent(new CustomEvent('pos-operacion-conflicto', {
                        detail: { operacion, error: mensaje },
                    }));
                    continue;
                }

                payloadResuelto = resolverPayload(operacion.payload, resultadosPorUuid);
                if (payloadResuelto === null) continue;
            }

            await actualizarOperacion(operacion.uuid, { estado: 'sincronizando' });

            try {
                const resultado = await enviarOperacion(operacion, payloadResuelto);
                resultadosPorUuid.set(operacion.uuid, resultado.id ?? null);

                window.dispatchEvent(new CustomEvent('pos-operacion-sincronizada', {
                    detail: { operacion, resultado },
                }));

                await actualizarOperacion(operacion.uuid, {
                    estado: 'completada',
                    resultado_id: resultado.id ?? null,
                });
            } catch (e) {
                const mensaje = e.message || 'Error desconocido al sincronizar.';

                await actualizarOperacion(operacion.uuid, {
                    estado: 'conflicto',
                    error: mensaje,
                });

                reportarConflictoAlServidor(operacion, mensaje);

                window.dispatchEvent(new CustomEvent('pos-operacion-conflicto', {
                    detail: { operacion, error: mensaje },
                }));
            }
        }

        // Limpieza: borrar las "completada" que ya nadie referencia como
        // dependencia (evita que la cola crezca para siempre).
        const pendientesDeUnaDependencia = new Set(
            (await listarOperaciones())
                .filter((op) => op.depende_de && op.estado !== 'completada')
                .map((op) => op.depende_de)
        );

        for (const op of await listarOperaciones()) {
            if (op.estado === 'completada' && !pendientesDeUnaDependencia.has(op.uuid)) {
                await eliminarOperacion(op.uuid);
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
