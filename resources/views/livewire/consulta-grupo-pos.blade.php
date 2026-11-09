<div x-data @abrir-consulta-grupo.window="$wire.abrir()">
    @if ($abierto)
        {{-- Mismo formato que el modal de Gestion de Prefacturas: .pos-pro,
             fondo azul claro, cabecera azul degradada y boton rojo Cerrar. --}}
        <div class="pos-pro" wire:key="consulta-grupo-modal">
            <div class="fixed inset-0 flex items-center justify-center" style="z-index:2147482600; padding:16px;">

                <div class="absolute inset-0" style="background:rgba(15,23,42,.62);" wire:click="cerrar"></div>

                <div class="relative bg-white rounded-xl shadow-xl border flex flex-col overflow-hidden"
                    style="z-index:2147482601;width:min(1120px, calc(100vw - 32px));max-height:calc(100dvh - 32px);border-color:#93c5fd;background:#eef6ff;">

                    <div class="px-6 py-3 flex items-center justify-between shrink-0"
                        style="background:linear-gradient(180deg,#2563eb 0%,#4f46e5 100%);color:#fff;">
                        <div>
                            <div class="text-lg font-bold" style="color:#fff;">Otras sucursales</div>
                            <div style="font-size:.75rem;color:#e0e7ff;">Solo para consultar: no se puede vender ni modificar desde aquí.</div>
                        </div>
                    </div>

                    <div class="px-6 py-3 shrink-0" style="background:#eef6ff;">
                        <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar producto..." autocomplete="off"
                            class="w-full p-2 border border-gray-300 rounded-full shadow" />
                    </div>

                    <div class="px-6 pb-3" style="flex:1;min-height:0;overflow:auto;">
                        <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:.5rem;overflow-x:auto;">
                            @include('partials.tabla-inventario-grupo', ['empresas' => $empresas, 'propia' => $propia, 'filas' => $filas, 'verStock' => $verStock, 'pos' => true])
                        </div>
                        @if ($filas->count() >= 30)
                            <p style="font-size:.75rem;color:#64748b;margin-top:.5rem;">Se muestran los primeros 30. Escribe más del nombre para afinar la búsqueda.</p>
                        @endif
                    </div>

                    <div class="px-6 py-3 flex justify-end shrink-0" style="background:#fff;border-top:1px solid #cbd5e1;">
                        <button type="button" wire:click="cerrar"
                            class="rounded-full text-white font-bold"
                            style="background:#ef4444;padding:.55rem 1.25rem;font-size:.9rem;border:none;cursor:pointer;">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
