<div x-data @abrir-consulta-grupo.window="$wire.abrir()">
    @if ($abierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3" wire:key="consulta-grupo-modal">
            <div class="w-full max-w-4xl rounded-xl bg-white shadow-lg" style="max-height:90vh;display:flex;flex-direction:column;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;">
                    <div>
                        <div style="font-weight:700;">Otras sucursales</div>
                        <div style="font-size:.75rem;color:#6b7280;">Solo para consultar: no se puede vender ni modificar desde aquí.</div>
                    </div>
                    <button type="button" wire:click="cerrar"
                        style="border:none;background:#e5e7eb;border-radius:9999px;width:32px;height:32px;cursor:pointer;font-size:16px;">✕</button>
                </div>

                <div style="padding:.75rem 1rem;">
                    <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar producto..." autocomplete="off"
                        class="w-full p-2 border border-gray-300 rounded-full shadow" />
                </div>

                <div style="overflow:auto;padding:0 1rem 1rem;">
                    @include('partials.tabla-inventario-grupo', ['empresas' => $empresas, 'propia' => $propia, 'filas' => $filas, 'verStock' => $verStock])
                    @if ($filas->count() >= 30)
                        <p style="font-size:.75rem;color:#6b7280;margin-top:.5rem;">Se muestran los primeros 30. Escribe más del nombre para afinar la búsqueda.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
