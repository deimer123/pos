<x-filament::page>
    <x-filament::section heading="Descargar copia de seguridad" class="combo-franja-azul">
        <p style="font-size:13px; color:#6b7280; margin-bottom:16px;">
            Genera un archivo .zip con toda la base de datos de esta instalación y las fotos guardadas (productos, taller, hotel). Guardalo en un lugar seguro (USB, tu nube personal) — esta app nunca sube nada a ningún servidor.
        </p>

        <x-filament::button wire:click="descargarRespaldo" color="primary" icon="heroicon-o-arrow-down-tray">
            Descargar copia de seguridad
        </x-filament::button>
    </x-filament::section>

    <div style="margin-top:24px;">
        <x-filament::section heading="Restaurar un respaldo" class="combo-franja-azul">
            <p style="font-size:13px; color:#dc2626; margin-bottom:16px; font-weight:600;">
                ⚠️ Restaurar reemplaza TODOS los datos actuales de esta instalación por los del archivo que subas. Esta acción no se puede deshacer.
            </p>

            <form wire:submit.prevent="prepararRestauracion" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" color="danger" icon="heroicon-o-arrow-up-tray">
                    Subir y preparar restauración
                </x-filament::button>
            </form>

            @if($restauracionLista)
                <div style="margin-top:20px; border:1px solid #fde68a; background:#fffbeb; border-radius:10px; padding:16px;">
                    <p style="font-size:13px; color:#92400e; margin:0 0 12px;">
                        El respaldo está listo. La app se va a cerrar y reabrir sola para terminar de restaurarlo.
                    </p>
                    <button
                        type="button"
                        id="btn-reiniciar-restaurar"
                        style="padding:.6rem 1.2rem; border-radius:8px; border:none; background:#dc2626; color:#fff; font-size:.9rem; cursor:pointer;"
                    >
                        Reiniciar y restaurar ahora
                    </button>
                    <p id="estado-restauracion" style="margin:.75rem 0 0; font-size:.8rem; color:#92400e;"></p>
                </div>
            @endif
        </x-filament::section>
    </div>

    <script>
        if (typeof window !== 'undefined' && window.__TAURI_INTERNALS__) {
            document.addEventListener('livewire:init', () => {
                const boton = document.getElementById('btn-reiniciar-restaurar');
                if (!boton) return;

                boton.addEventListener('click', async () => {
                    const estado = document.getElementById('estado-restauracion');
                    boton.disabled = true;
                    boton.textContent = 'Restaurando...';

                    try {
                        await window.__TAURI_INTERNALS__.invoke('preparar_restauracion');
                        const { relaunch } = await import('@tauri-apps/plugin-process');
                        estado.textContent = 'Reiniciando...';
                        await relaunch();
                    } catch (error) {
                        console.warn('Sistema POS Local: fallo la restauracion', error);
                        estado.textContent = 'No se pudo restaurar. Intenta de nuevo.';
                        boton.disabled = false;
                        boton.textContent = 'Reiniciar y restaurar ahora';
                    }
                });
            });
        }
    </script>
</x-filament::page>
