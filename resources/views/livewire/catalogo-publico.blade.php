<div style="min-height:100vh; background:#f8fafc;">
    <div style="background:#4f46e5; color:white; padding:24px 16px; text-align:center;">
        @if($this->config->logo)
            <img src="{{ $this->config->logo_url }}" alt="Logo" style="width:64px; height:64px; object-fit:cover; border-radius:50%; margin:0 auto 8px; display:block; border:2px solid white;">
        @endif
        <div style="font-size:22px; font-weight:900;">{{ $this->config->nombre_empresa }}</div>
        @if($this->config->lema)
            <div style="font-size:13px; opacity:.9; margin-top:2px;">{{ $this->config->lema }}</div>
        @endif
    </div>

    <div style="padding:8px 16px; background:white; border-bottom:1px solid #e5e7eb; display:flex; justify-content:center;">
        <input type="text" wire:model.live.debounce.300ms="busqueda" placeholder="🔍 Buscar..."
            style="width:100%; max-width:280px; box-sizing:border-box; border:1px solid #d1d5db; border-radius:999px; padding:5px 14px; font-size:13px;">
    </div>

    @if($this->familias->count() > 1)
    <div style="display:flex; gap:8px; overflow-x:auto; padding:12px 16px; background:white; border-bottom:1px solid #e5e7eb;">
        <button type="button" wire:click="$set('familiaActiva', null)"
            style="flex-shrink:0; border:none; border-radius:999px; padding:6px 16px; font-size:13px; font-weight:700; cursor:pointer;
                background:{{ ! $familiaActiva ? '#4f46e5' : '#f3f4f6' }}; color:{{ ! $familiaActiva ? 'white' : '#374151' }};">
            Todo
        </button>
        @foreach($this->familias as $familia)
        <button type="button" wire:click="$set('familiaActiva', {{ $familia->id }})"
            style="flex-shrink:0; border:none; border-radius:999px; padding:6px 16px; font-size:13px; font-weight:700; cursor:pointer;
                background:{{ $familiaActiva === $familia->id ? '#4f46e5' : '#f3f4f6' }}; color:{{ $familiaActiva === $familia->id ? 'white' : '#374151' }};">
            {{ $familia->nombre }}
        </button>
        @endforeach
    </div>
    @endif

    <style>html { scroll-behavior: smooth; }</style>

    @php
        // Lista de subfamilias visibles (según filtro actual) para el menú lateral.
        $menuSubfamilias = collect();
        foreach ($this->productosFiltrados as $idFamilia => $productosFamilia) {
            foreach ($productosFamilia->groupBy(fn($p) => $p->id_familia2) as $idSubfamilia => $productosSub) {
                $subfamilia = $productosSub->first()->subfamilia;
                if ($subfamilia) {
                    $menuSubfamilias->push($subfamilia);
                }
            }
        }
    @endphp

    <div style="max-width:960px; margin:0 auto; padding:16px; display:flex; align-items:flex-start; gap:16px;">
        @if($menuSubfamilias->isNotEmpty())
        <nav style="flex:0 0 130px; position:sticky; top:16px; max-height:calc(100vh - 32px); overflow-y:auto; background:white; border-radius:12px; border:1px solid #e5e7eb; padding:10px;">
            <div style="font-size:10px; font-weight:800; color:#9ca3af; text-transform:uppercase; margin-bottom:6px;">Ir a</div>
            @foreach($menuSubfamilias as $subfamiliaMenu)
                <a href="#subfam-{{ $subfamiliaMenu->id_familia2 }}"
                    style="display:block; font-size:12px; color:#4f46e5; text-decoration:none; padding:4px 0; border-bottom:1px solid #f3f4f6; font-weight:600;">
                    {{ $subfamiliaMenu->nombre }}
                </a>
            @endforeach
        </nav>
        @endif

        <div style="flex:1; min-width:0;">
        @forelse($this->productosFiltrados as $idFamilia => $productosFamilia)
            @php $familia = $this->familias->firstWhere('id', $idFamilia); @endphp
            <div style="margin-bottom:28px;">
                <h2 style="font-size:17px; font-weight:800; color:#1f2937; margin:0 0 12px; padding-bottom:6px; border-bottom:2px solid #4f46e5;">
                    {{ $familia->nombre ?? 'Otros' }}
                </h2>

                @foreach($productosFamilia->groupBy(fn($p) => $p->id_familia2) as $idSubfamilia => $productosSub)
                    @php $subfamilia = $productosSub->first()->subfamilia; @endphp
                    @if($subfamilia)
                        <div id="subfam-{{ $subfamilia->id_familia2 }}" style="font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; margin:14px 0 8px; scroll-margin-top:16px;">
                            {{ $subfamilia->nombre }}
                        </div>
                    @endif

                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px;">
                        @foreach($productosSub as $producto)
                            @php
                                $tieneImagen = !empty($producto->foto) && $producto->foto !== 'NULL';
                                $urlImagen = $tieneImagen ? asset('storage/' . $producto->foto) : asset('images/sin-imagen.png');
                                $disponible = (float) $producto->existencias > 0;
                                $precioTexto = '$' . number_format((float) $producto->precio_venta1, 0, ',', '.');
                            @endphp
                            <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column;">
                                <img src="{{ $urlImagen }}" alt="{{ $producto->descripcion_larga }}"
                                    x-data
                                    @click="$dispatch('ver-imagen-catalogo', {
                                        url: @js($urlImagen),
                                        nombre: @js($producto->descripcion_larga),
                                        descripcion: @js($producto->descripcion_catalogo),
                                        @if($this->config->catalogo_mostrar_precio) precio: @js($precioTexto), @endif
                                        @if($this->config->catalogo_mostrar_disponibilidad) disponible: @js($disponible ? 'Disponible' : 'No disponible'), @endif
                                    })"
                                    style="width:100%; height:120px; object-fit:cover; display:block; cursor:zoom-in;">
                                <div style="padding:10px; display:flex; flex-direction:column; flex:1;">
                                    <div style="font-size:13px; font-weight:700; color:#1f2937; line-height:1.3;">{{ $producto->descripcion_larga }}</div>
                                    @if($producto->descripcion_catalogo)
                                        <div style="font-size:11px; color:#6b7280; margin-top:4px; line-height:1.3;">{{ $producto->descripcion_catalogo }}</div>
                                    @endif

                                    <div style="margin-top:auto; padding-top:6px;">
                                        @if($this->config->catalogo_mostrar_disponibilidad)
                                            <div style="font-size:10px; font-weight:700; margin-bottom:2px; color:{{ $disponible ? '#16a34a' : '#dc2626' }};">
                                                {{ $disponible ? '✔ Disponible' : '✕ No disponible' }}
                                            </div>
                                        @endif
                                        @if($this->config->catalogo_mostrar_precio)
                                        <div style="font-size:15px; font-weight:900; color:#4f46e5;">
                                            {{ $precioTexto }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <div style="font-size:40px;">🛍️</div>
                <div style="margin-top:12px; font-size:14px;">Todavía no hay productos disponibles en el catálogo.</div>
            </div>
        @endforelse
        </div>
    </div>

    <div x-data="{ imagenUrl: null, imagenNombre: null, imagenDescripcion: null, imagenPrecio: null, imagenDisponible: null }"
        @ver-imagen-catalogo.window="
            imagenUrl = $event.detail.url;
            imagenNombre = $event.detail.nombre;
            imagenDescripcion = $event.detail.descripcion;
            imagenPrecio = $event.detail.precio;
            imagenDisponible = $event.detail.disponible;
        ">
        <div x-show="imagenUrl" x-transition x-cloak
            style="position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:50; display:flex; align-items:center; justify-content:center; padding:20px;"
            @click="imagenUrl = null">
            <div style="max-width:90vw; max-height:90vh; text-align:center;" @click.stop>
                <img :src="imagenUrl" style="max-width:100%; max-height:70vh; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.4);">
                <div style="color:white; margin-top:12px; font-size:16px; font-weight:700;" x-text="imagenNombre"></div>
                <div x-show="imagenDescripcion" style="color:#d1d5db; margin-top:4px; font-size:13px; max-width:400px; margin-left:auto; margin-right:auto;" x-text="imagenDescripcion"></div>
                <div x-show="imagenDisponible" style="margin-top:6px; font-size:12px; font-weight:700;" :style="imagenDisponible === 'Disponible' ? 'color:#4ade80' : 'color:#f87171'" x-text="imagenDisponible"></div>
                <div x-show="imagenPrecio" style="color:white; margin-top:8px; font-size:20px; font-weight:900;" x-text="imagenPrecio"></div>
                <button type="button" @click="imagenUrl = null"
                    style="margin-top:14px; border:none; border-radius:999px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer; background:white; color:#1f2937;">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
