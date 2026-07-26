/**
 * Revisa actualizaciones de Sistema POS Offline contra el manifiesto
 * configurado en tauri.conf.json (plugins.updater.endpoints). A proposito
 * SOLO se incluye en la pantalla de login (ver AdminPanelProvider,
 * render hook AUTH_LOGIN_FORM_AFTER) y no en el layout del POS: revisar
 * a mitad de una venta e interrumpir al cajero para instalar no tiene
 * sentido -- el momento natural es antes de entrar a trabajar.
 *
 * Si hay una version nueva, NO se instala sola: se bloquea el login con
 * un aviso hasta que alguien le de "Actualizar ahora" explicitamente.
 *
 * Solo hace algo dentro de la app de escritorio -- Tauri inyecta el
 * global __TAURI_INTERNALS__ en el webview; en un navegador normal
 * (droplet, o cualquier empresa que entre por internet) no existe, y el
 * import dinamico ni siquiera se intenta.
 */
if (typeof window !== 'undefined' && window.__TAURI_INTERNALS__) {
    (async () => {
        try {
            const { check } = await import('@tauri-apps/plugin-updater');
            const { relaunch } = await import('@tauri-apps/plugin-process');

            const update = await check();

            if (update) {
                mostrarAvisoActualizacion(update, relaunch);
            }
        } catch (error) {
            // Sin conexion, o el endpoint no respondio -- no interrumpe el
            // login normal, se vuelve a intentar la proxima vez que se
            // muestre esta pantalla.
            console.warn('Sistema POS Offline: no se pudo revisar actualizaciones', error);
        }
    })();
}

function mostrarAvisoActualizacion(update, relaunch) {
    const iniciar = () => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; inset: 0; z-index: 999999;
            background: rgba(15, 23, 42, .85);
            display: flex; align-items: center; justify-content: center;
            font-family: system-ui, sans-serif;
        `;

        // Cubre toda la pantalla (login incluido, que queda debajo sin
        // poder recibir clics) -- asi no se puede iniciar sesion con una
        // version vieja sin antes decidir actualizar.
        overlay.innerHTML = `
            <div style="background:#1e293b;color:#e2e8f0;border-radius:12px;padding:2rem;max-width:420px;width:calc(100% - 32px);text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5);">
                <div style="font-size:2.5rem;margin-bottom:.5rem;">⬇️</div>
                <h2 style="margin:0 0 .5rem;font-size:1.15rem;">Actualización disponible</h2>
                <p style="margin:0 0 1.25rem;font-size:.9rem;color:#94a3b8;">
                    Hay una nueva versión de Sistema POS Offline (v${update.version}) lista para instalar.
                    Debes actualizar antes de iniciar sesión.
                </p>
                <button id="turion-actualizar-btn" style="width:100%;padding:.7rem;border-radius:8px;border:none;background:#2563eb;color:#fff;font-size:1rem;cursor:pointer;">
                    Actualizar ahora
                </button>
                <p id="turion-actualizar-estado" style="margin:.75rem 0 0;font-size:.8rem;color:#94a3b8;"></p>
            </div>
        `;

        document.body.appendChild(overlay);

        document.getElementById('turion-actualizar-btn').addEventListener('click', async (evento) => {
            const boton = evento.currentTarget;
            const estado = document.getElementById('turion-actualizar-estado');
            boton.disabled = true;
            boton.textContent = 'Descargando...';

            try {
                // Apaga el servidor PHP local ANTES de instalar: si no, el
                // instalador cierra la ventana pero deja ese proceso hijo
                // huerfano bloqueando sus propios archivos ("Error opening
                // file for writing" al reinstalar encima).
                try {
                    await window.__TAURI_INTERNALS__.invoke('detener_servidor_local');
                } catch (e) {
                    console.warn('Sistema POS Offline: no se pudo detener el servidor local antes de actualizar', e);
                }

                await update.downloadAndInstall();
                estado.textContent = 'Reiniciando...';
                await relaunch();
            } catch (error) {
                console.warn('Sistema POS Offline: fallo la actualizacion', error);
                estado.textContent = 'No se pudo actualizar. Verifica tu conexión e intenta de nuevo.';
                boton.disabled = false;
                boton.textContent = 'Actualizar ahora';
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
}
