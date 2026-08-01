<x-filament::page>
    @php
        $habitaciones = $this->habitaciones();
        $reservas = $this->reservas();
        $money = fn ($valor) => '$ ' . number_format((float) $valor, 0, ',', '.');
        $cantidadFmt = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');
        $colorEstado = ['reservada' => '#1e90ff', 'checkin' => '#ffa502', 'checkout' => '#2ed573'];
        $labelEstado = ['reservada' => 'Reservada', 'checkin' => 'Ocupada, sin facturar', 'checkout' => 'Facturada'];
    @endphp

    <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; margin-bottom:20px;">
        <div>
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">Habitación</label>
            <select wire:model.live="habitacionId"
                style="border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:13px; min-width:160px;">
                @foreach($habitaciones as $habitacion)
                    <option value="{{ $habitacion->id }}">{{ $habitacion->numero }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">Desde</label>
            <input type="date" wire:model.live="desde"
                style="border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:13px;">
        </div>
        <div>
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:4px;">Hasta</label>
            <input type="date" wire:model.live="hasta"
                style="border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:13px;">
        </div>
    </div>

    <x-filament::section class="combo-franja-azul">
        @if($habitaciones->isEmpty())
            <p style="font-size:13px; color:#6b7280; text-align:center; padding:20px;">No hay habitaciones configuradas.</p>
        @elseif($reservas->isEmpty())
            <p style="font-size:13px; color:#6b7280; text-align:center; padding:20px;">📭 Esta habitación no tuvo estadías en el rango seleccionado.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#2f3542; color:#fff;">
                            <th style="padding:8px; text-align:left;">Fecha</th>
                            <th style="padding:8px; text-align:left;">Producto</th>
                            <th style="padding:8px;">Cantidad</th>
                            <th style="padding:8px;">Precio Unit.</th>
                            <th style="padding:8px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservas as $reserva)
                            <tr style="background:#dbeafe;">
                                <td colspan="5" style="padding:10px 8px;">
                                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px;">
                                        <div>
                                            <strong>Reserva #{{ $reserva->numero_reserva }} — {{ $reserva->huesped_nombre }}</strong>
                                            <span style="display:inline-block; margin-left:8px; padding:2px 10px; border-radius:5px; background:{{ $colorEstado[$reserva->estado] ?? '#6b7280' }}; color:#fff; font-size:11px; font-weight:600;">
                                                {{ $labelEstado[$reserva->estado] ?? $reserva->estado }}
                                            </span>
                                            <span style="color:#4b5563; font-size:12px; margin-left:8px;">
                                                {{ $reserva->fecha_checkin->format('d/m/Y') }} → {{ $reserva->fecha_checkout?->format('d/m/Y') ?? 'Sin definir' }}
                                            </span>
                                        </div>
                                        <div style="font-size:12px; color:#1f2937; white-space:nowrap;">
                                            Hospedaje ({{ $reserva->numero_noches }} noche{{ $reserva->numero_noches == 1 ? '' : 's' }}): <strong>{{ $money($reserva->total_estimado) }}</strong>
                                            &nbsp;·&nbsp; Abono: <strong>{{ $money($reserva->abono_monto) }}</strong>
                                            &nbsp;·&nbsp; Saldo: <strong style="color:{{ $reserva->saldo_pendiente > 0 ? '#dc2626' : '#065f46' }};">{{ $money($reserva->saldo_pendiente) }}</strong>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            @forelse($reserva->consumos as $consumo)
                                <tr style="{{ $loop->even ? 'background:#f1f2f6;' : '' }}">
                                    <td style="padding:8px; text-align:left;">{{ $consumo->created_at->format('d/m/Y H:i') }}</td>
                                    <td style="padding:8px; text-align:left;">{{ $consumo->descripcion }}</td>
                                    <td style="padding:8px; text-align:center;">{{ $cantidadFmt($consumo->cantidad) }}</td>
                                    <td style="padding:8px; text-align:center;">{{ $money($consumo->precio_unitario) }}</td>
                                    <td style="padding:8px; text-align:center; font-weight:600;">{{ $money($consumo->subtotal) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding:10px 8px; text-align:center; color:#9ca3af; font-size:12px;">
                                        Sin productos agregados a esta cuenta.
                                    </td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament::page>
