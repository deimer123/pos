/**
 * Revisa actualizaciones de Turion contra el manifiesto configurado en
 * tauri.conf.json (plugins.updater.endpoints) y, si hay una version nueva
 * firmada correctamente, la descarga e instala sola.
 *
 * Solo hace algo dentro de la app de escritorio de Turion -- Tauri inyecta
 * el global __TAURI_INTERNALS__ en el webview; en un navegador normal
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
                console.log(`Turion: actualización ${update.version} disponible, descargando...`);
                await update.downloadAndInstall();
                await relaunch();
            }
        } catch (error) {
            // Sin conexion, o el endpoint no respondio -- no interrumpe el
            // uso normal del POS, se vuelve a intentar en el proximo arranque.
            console.warn('Turion: no se pudo revisar actualizaciones', error);
        }
    })();
}
