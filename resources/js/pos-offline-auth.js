/**
 * "Desbloqueo offline": permite volver a confirmar la identidad del
 * cajero en ESTE dispositivo especifico sin necesitar internet, para
 * cuando se recarga la pagina o pasa un rato de inactividad estando sin
 * conexion. Esto NO crea una sesion de Laravel nueva -- eso requiere
 * siempre tocar el servidor en algun momento. Lo que hace es volver a
 * mostrar la app si la contraseña coincide con la que se confirmo la
 * ultima vez que se "activo" el acceso offline en este dispositivo (con
 * internet), asumiendo que la sesion de Laravel que ya existe sigue viva.
 *
 * La contraseña real de la cuenta NUNCA se manda al servidor mas que para
 * confirmarla una vez (POST /pos/activar-offline, que solo hace
 * Hash::check y no devuelve nada sensible). El navegador deriva su PROPIO
 * hash aparte (PBKDF2 con sal propia) y lo guarda en IndexedDB -- ese hash
 * nunca sale del dispositivo.
 */

import { abrirDB, STORE_AUTH_OFFLINE } from './pos-offline-db.js';

const ITERACIONES_PBKDF2 = 150000;
const MAX_INTENTOS = 5;
const BLOQUEO_MS = 5 * 60 * 1000; // 5 minutos
const LOCK_ATTEMPTS_KEY = 'pos_offline_intentos';
const LOCK_UNTIL_KEY = 'pos_offline_bloqueado_hasta';
const UNLOCKED_KEY = 'pos_offline_desbloqueado';

function deviceId() {
    let id = localStorage.getItem('pos_device_id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('pos_device_id', id);
    }
    return id;
}

function bytesToBase64(bytes) {
    return btoa(String.fromCharCode(...bytes));
}

function base64ToBytes(b64) {
    return Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
}

async function derivarHash(password, saltBytes) {
    const enc = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
        { name: 'PBKDF2', salt: saltBytes, iterations: ITERACIONES_PBKDF2, hash: 'SHA-256' },
        keyMaterial,
        256,
    );
    return new Uint8Array(bits);
}

function hashesIguales(a, b) {
    if (a.length !== b.length) return false;
    let diff = 0;
    for (let i = 0; i < a.length; i++) diff |= a[i] ^ b[i];
    return diff === 0;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function guardarCredencialLocal(userId, password) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const hash = await derivarHash(password, salt);

    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_AUTH_OFFLINE, 'readwrite');
        tx.objectStore(STORE_AUTH_OFFLINE).put({
            user_id: userId,
            device_id: deviceId(),
            salt: bytesToBase64(salt),
            hash: bytesToBase64(hash),
            activado_en: Date.now(),
        });
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

async function credencialesLocales() {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_AUTH_OFFLINE, 'readonly');
        const request = tx.objectStore(STORE_AUTH_OFFLINE).getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

/**
 * Confirma la contraseña contra el servidor (una sola vez, con internet)
 * y activa el desbloqueo offline en este dispositivo si es correcta.
 */
export async function activarAccesoOffline(password) {
    const response = await fetch('/pos/activar-offline', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ password }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Contraseña incorrecta.');
    }

    await guardarCredencialLocal(data.user_id, password);
    return true;
}

export async function hayAccesoOfflineActivado() {
    const credenciales = await credencialesLocales();
    return credenciales.length > 0;
}

function bloqueoActivo() {
    const hasta = Number(localStorage.getItem(LOCK_UNTIL_KEY) || 0);
    return Date.now() < hasta;
}

function segundosRestantesBloqueo() {
    const hasta = Number(localStorage.getItem(LOCK_UNTIL_KEY) || 0);
    return Math.max(0, Math.ceil((hasta - Date.now()) / 1000));
}

function registrarIntentoFallido() {
    const intentos = Number(localStorage.getItem(LOCK_ATTEMPTS_KEY) || 0) + 1;
    localStorage.setItem(LOCK_ATTEMPTS_KEY, String(intentos));

    if (intentos >= MAX_INTENTOS) {
        localStorage.setItem(LOCK_UNTIL_KEY, String(Date.now() + BLOQUEO_MS));
        localStorage.setItem(LOCK_ATTEMPTS_KEY, '0');
    }
}

function limpiarIntentos() {
    localStorage.removeItem(LOCK_ATTEMPTS_KEY);
    localStorage.removeItem(LOCK_UNTIL_KEY);
}

/**
 * Compara la contraseña contra CUALQUIERA de las credenciales guardadas
 * en este dispositivo (puede haber mas de un cajero con acceso offline
 * activado en la misma caja).
 */
export async function verificarPasswordOffline(password) {
    if (bloqueoActivo()) {
        throw new Error('Demasiados intentos. Espera ' + segundosRestantesBloqueo() + ' segundos.');
    }

    const credenciales = await credencialesLocales();

    for (const cred of credenciales) {
        const salt = base64ToBytes(cred.salt);
        const hashIntentado = await derivarHash(password, salt);
        const hashGuardado = base64ToBytes(cred.hash);

        if (hashesIguales(hashIntentado, hashGuardado)) {
            limpiarIntentos();
            sessionStorage.setItem(UNLOCKED_KEY, '1');
            return true;
        }
    }

    registrarIntentoFallido();
    return false;
}

export function yaDesbloqueadoEnEstaPestana() {
    return sessionStorage.getItem(UNLOCKED_KEY) === '1';
}

window.PosOfflineAuth = {
    activarAccesoOffline,
    hayAccesoOfflineActivado,
    verificarPasswordOffline,
    yaDesbloqueadoEnEstaPestana,
};
