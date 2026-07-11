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

    <div style="max-width:720px; margin:0 auto; padding:16px;">
        @forelse($this->productosFiltrados as $idFamilia => $productosFamilia)
            @php $familia = $this->familias->firstWhere('id', $idFamilia); @endphp
            <div style="margin-bottom:28px;">
                <h2 style="font-size:17px; font-weight:800; color:#1f2937; margin:0 0 12px; padding-bottom:6px; border-bottom:2px solid #4f46e5;">
                    {{ $familia->nombre ?? 'Otros' }}
                </h2>

                @foreach($productosFamilia->groupBy(fn($p) => $p->id_familia2) as $idSubfamilia => $productosSub)
                    @php $subfamilia = $productosSub->first()->subfamilia; @endphp
                    @if($subfamilia)
                        <div style="font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; margin:14px 0 8px;">
                            {{ $subfamilia->nombre }}
                        </div>
                    @endif

                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px;">
                        @foreach($productosSub as $producto)
                            @php
                                $tieneImagen = !empty($producto->foto) && $producto->foto !== 'NULL';
                                $urlImagen = $tieneImagen ? asset('storage/' . $producto->foto) : asset('images/sin-imagen.png');
                            @endphp
                            <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08);">
                                <img src="{{ $urlImagen }}" alt="{{ $producto->descripcion_larga }}" style="width:100%; height:120px; object-fit:cover; display:block;">
                                <div style="padding:10px;">
                                    <div style="font-size:13px; font-weight:700; color:#1f2937; line-height:1.3;">{{ $producto->descripcion_larga }}</div>
                                    @if($producto->descripcion_catalogo)
                                        <div style="font-size:11px; color:#6b7280; margin-top:4px; line-height:1.3;">{{ $producto->descripcion_catalogo }}</div>
                                    @endif
                                    <div style="font-size:15px; font-weight:900; color:#4f46e5; margin-top:6px;">
                                        ${{ number_format((float) $producto->precio_venta1, 0, ',', '.') }}
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
