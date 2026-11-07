<x-filament-panels::page>
    <p style="font-size:.9rem;color:#4b5563;">
        Existencias de cada empresa del grupo, <strong>solo para consultar</strong>. Los productos se emparejan por nombre exacto.
        Un guion (—) significa que esa empresa no tiene el producto.
    </p>

    <x-filament::input.wrapper>
        <x-filament::input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar producto..." />
    </x-filament::input.wrapper>

    <x-filament::section>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e5e7eb;">
                        <th style="padding:.5rem;">Producto</th>
                        @foreach ($empresas as $id => $nombre)
                            <th style="padding:.5rem;text-align:right;">
                                {{ $nombre }} @if ($id === $propia) <span style="font-weight:400;color:#6b7280;">(esta)</span> @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:.5rem;font-weight:600;">{{ $fila->nombre }}</td>
                            @foreach ($empresas as $id => $nombre)
                                @php $existencias = $fila->{'existencias_' . $id}; @endphp
                                <td style="padding:.5rem;text-align:right;">
                                    @if ($existencias === null)
                                        —
                                    @else
                                        {{ number_format((float) $existencias, 0, ',', '.') }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($empresas) + 1 }}" style="padding:1rem;text-align:center;color:#6b7280;">No se encontraron productos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;">
            <x-filament::pagination :paginator="$filas" />
        </div>
    </x-filament::section>
</x-filament-panels::page>
