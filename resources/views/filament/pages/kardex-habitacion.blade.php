<x-filament::page>
    @php
        $habitaciones = $this->habitaciones();
        $reservas = $this->reservas();
        $money = fn ($valor) => '$ ' . number_format((float) $valor, 0, ',', '.');
        $cantidadFmt = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');
        $colorEstado = ['reservada' => '#93c5fd', 'checkin' => '#fbbf24', 'checkout' => '#22c55e'];
        $labelEstado = ['reservada' => 'Reservada', 'checkin' => 'Ocupada, sin facturar', 'checkout' => 'Facturada'];
    @endphp

    <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; margin-bottom:24px;">
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

    @if($habitaciones->isEmpty())
        <x-filament::section class="combo-franja-azul">
            <p style="font-size:13px; color:#6b7280;">No hay habitaciones configuradas.</p>
        </x-filament::section>
    @elseif($reservas->isEmpty())
        <x-filament::section class="combo-franja-azul">
            <p style="font-size:13px; color:#6b7280;">Esta habitación no tuvo estadías en el rango seleccionado.</p>
        </x-filament::section>
    @else
        <div style="display:flex; flex-direction:column; gap:16px;">
            @foreach($reservas as $reserva)
                <x-filament::section class="combo-franja-azul">
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px; margin-bottom:8px;">
                        <div>
                            <span style="font-weight:700; font-size:14px;">Reserva #{{ $reserva->numero_reserva }} — {{ $reserva->huesped_nombre }}</span>
                            <span style="display:inline-block; margin-left:8px; padding:2px 8px; border-radius:6px; background:{{ $colorEstado[$reserva->estado] ?? '#e5e7eb' }}; color:#111827; font-size:11px; font-weight:600;">
                                {{ $labelEstado[$reserva->estado] ?? $reserva->estado }}
                            </span>
                        </div>
                        <div style="font-size:12px; color:#6b7280;">
                            {{ $reserva->fecha_checkin->format('d/m/Y') }}
                            &rarr;
                            {{ $reserva->fecha_checkout?->format('d/m/Y') ?? 'Sin definir' }}
                        </div>
                    </div>

                    @if($reserva->consumos->isEmpty())
                        <p style="font-size:12px; color:#9ca3af;">Sin productos agregados a la cuenta.</p>
                    @else
                        <div style="overflow-x:auto;">
                            <table style="width:100%; font-size:13px; border-collapse:collapse;">
                                <thead>
                                    <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                                        <th style="padding:6px 12px 6px 0;">Producto</th>
                                        <th style="padding:6px 12px 6px 0;">Cantidad</th>
                                        <th style="padding:6px 12px 6px 0;">Precio Unit.</th>
                                        <th style="padding:6px 12px 6px 0;">Subtotal</th>
                                        <th style="padding:6px 12px 6px 0;">Agregado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reserva->consumos as $consumo)
                                        <tr style="border-bottom:1px solid #f3f4f6;">
                                            <td style="padding:6px 12px 6px 0;">{{ $consumo->descripcion }}</td>
                                            <td style="padding:6px 12px 6px 0;">{{ $cantidadFmt($consumo->cantidad) }}</td>
                                            <td style="padding:6px 12px 6px 0;">{{ $money($consumo->precio_unitario) }}</td>
                                            <td style="padding:6px 12px 6px 0; font-weight:600;">{{ $money($consumo->subtotal) }}</td>
                                            <td style="padding:6px 12px 6px 0; color:#6b7280; font-size:12px;">{{ $consumo->created_at->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div style="display:flex; flex-wrap:wrap; gap:24px; margin-top:12px; padding-top:12px; border-top:1px solid #e5e7eb; font-size:13px;">
                        <div>Hospedaje ({{ $reserva->numero_noches }} noche{{ $reserva->numero_noches == 1 ? '' : 's' }}): <strong>{{ $money($reserva->total_estimado) }}</strong></div>
                        <div>Productos: <strong>{{ $money($reserva->total_consumos) }}</strong></div>
                        <div>Abono: <strong>{{ $money($reserva->abono_monto) }}</strong></div>
                        <div>Saldo pendiente: <strong style="color:{{ $reserva->saldo_pendiente > 0 ? '#dc2626' : '#065f46' }};">{{ $money($reserva->saldo_pendiente) }}</strong></div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament::page>
