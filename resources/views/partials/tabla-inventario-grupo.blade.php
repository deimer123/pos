{{-- Tabla de solo lectura: cantidad y precio de venta de cada producto en cada
     empresa del grupo. Variables: $empresas [id => nombre], $propia, $filas,
     $verStock, $pos (opcional: dentro de .pos-pro, que ya da el estilo de
     tabla del POS). Sin botones ni acciones. --}}
@php $pos = $pos ?? false; @endphp
<table style="width:100%;border-collapse:collapse;font-size:.85rem;">
    <thead>
        <tr @unless($pos) style="border-bottom:1px solid #e5e7eb;" @endunless>
            <th rowspan="2" style="padding:.5rem;text-align:left;vertical-align:bottom;">Producto</th>
            @foreach ($empresas as $id => $nombre)
                <th colspan="{{ $verStock ? 2 : 1 }}" style="padding:.5rem;text-align:center;{{ $pos ? '' : 'border-left:1px solid #e5e7eb;' }}">
                    {{ $nombre }} @if ($id === $propia) <span style="font-weight:400;{{ $pos ? 'opacity:.8;' : 'color:#6b7280;' }}">(esta)</span> @endif
                </th>
            @endforeach
        </tr>
        <tr @unless($pos) style="border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:.75rem;" @else style="font-size:.75rem;" @endunless>
            @foreach ($empresas as $id => $nombre)
                @if ($verStock)
                    <th style="padding:.25rem .5rem;text-align:right;{{ $pos ? '' : 'border-left:1px solid #e5e7eb;' }}">Cantidad</th>
                @endif
                <th style="padding:.25rem .5rem;text-align:right;{{ ! $pos && ! $verStock ? 'border-left:1px solid #e5e7eb;' : '' }}">Precio</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr @unless($pos) style="border-bottom:1px solid #f3f4f6;" @endunless>
                <td style="padding:.5rem;font-weight:600;">{{ $fila->nombre }}</td>
                @foreach ($empresas as $id => $nombre)
                    @php
                        $existencias = $fila->{'existencias_' . $id};
                        $precio = $fila->{'precio_' . $id};
                        $tiene = $existencias !== null || $precio !== null;
                    @endphp
                    @if ($verStock)
                        <td style="padding:.5rem;text-align:right;{{ $pos ? '' : 'border-left:1px solid #f3f4f6;' }}">
                            {{ $existencias === null ? '—' : number_format((float) $existencias, 0, ',', '.') }}
                        </td>
                    @endif
                    <td style="padding:.5rem;text-align:right;{{ ! $pos && ! $verStock ? 'border-left:1px solid #f3f4f6;' : '' }}">
                        {{ $tiene && $precio !== null ? '$ ' . number_format((float) $precio, 0, ',', '.') : '—' }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ 1 + count($empresas) * ($verStock ? 2 : 1) }}" style="padding:1rem;text-align:center;color:#6b7280;">No se encontraron productos.</td></tr>
        @endforelse
    </tbody>
</table>
