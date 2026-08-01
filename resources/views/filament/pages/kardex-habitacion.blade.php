<x-filament::page>
    @php
        $habitaciones = $this->habitaciones();
        $reservas = $this->reservas();
        $money = fn ($valor) => '$ ' . number_format((float) $valor, 0, ',', '.');
        $cantidadFmt = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ',');
        $colorEstado = ['reservada' => '#1e90ff', 'checkin' => '#ffa502', 'checkout' => '#2ed573'];
        $labelEstado = ['reservada' => 'Reservada', 'checkin' => 'Ocupada, sin facturar', 'checkout' => 'Facturada'];
        $climaLabel = ['aire' => '❄️ Aire acondicionado', 'ventilador' => '🌀 Ventilador', 'ninguno' => 'Sin aire/ventilador'];
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

    <style>
        .kh-fila-grid {
            display: grid;
            grid-template-columns: 20px 70px 1fr 170px 190px 140px;
            align-items: center;
            gap: 8px;
        }
        .kh-resumen {
            cursor: pointer;
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            list-style: none;
        }
        .kh-resumen::-webkit-details-marker { display: none; }
        .kh-resumen:hover { background: #f9fafb; }
        .kh-flecha { transition: transform .15s ease; color: #6b7280; }
        details[open] > .kh-resumen .kh-flecha { transform: rotate(90deg); }
    </style>

    <x-filament::section class="combo-franja-azul">
        @if($habitaciones->isEmpty())
            <p style="font-size:13px; color:#6b7280; text-align:center; padding:20px;">No hay habitaciones configuradas.</p>
        @elseif($reservas->isEmpty())
            <p style="font-size:13px; color:#6b7280; text-align:center; padding:20px;">📭 Esta habitación no tuvo estadías en el rango seleccionado.</p>
        @else
            <div class="kh-fila-grid" style="background:#2f3542; color:#fff; font-size:12px; font-weight:600; padding:10px 8px; border-radius:6px 6px 0 0;">
                <div></div>
                <div>Reserva</div>
                <div>Huésped</div>
                <div>Estado</div>
                <div>Check-in → Check-out</div>
                <div style="text-align:right;">Total</div>
            </div>
            <div style="display:flex; flex-direction:column; border:1px solid #e5e7eb; border-top:none;">
                @foreach($reservas as $reserva)
                    @php
                        // Si ya se facturó, el total real es el de la factura (lo que
                        // efectivamente se cobró, incluyendo lo pagado al check-out) --
                        // no el saldo_pendiente estimado, que solo resta el abono inicial
                        // y quedaría mostrando "pendiente" una cuenta ya cerrada.
                        $totalReal = $reserva->factura ? (float) $reserva->factura->total : $reserva->saldo_pendiente;
                        $etiquetaTotal = $reserva->factura ? 'Total facturado' : 'Saldo pendiente';

                        // fecha_checkout es la fecha PLANEADA al reservar (puede quedar
                        // en blanco si el huesped no sabia cuando se iba); una vez
                        // facturada, lo que realmente paso es checkout_real_at (se llena
                        // al momento de facturar, ver FacturarVentaService) -- mostrar la
                        // planeada ahi seguia diciendo "Sin definir" en estadias ya
                        // cerradas y pagadas.
                        $checkoutMostrado = $reserva->estado === 'checkout' && $reserva->checkout_real_at
                            ? $reserva->checkout_real_at->format('d/m/Y')
                            : ($reserva->fecha_checkout?->format('d/m/Y') ?? 'Sin definir');
                    @endphp
                    <details>
                        <summary class="kh-fila-grid kh-resumen">
                            <span class="kh-flecha">▶</span>
                            <span style="font-size:13px; font-weight:600;">#{{ $reserva->numero_reserva }}</span>
                            <span style="font-size:13px;">{{ $reserva->huesped_nombre }}</span>
                            <span style="display:inline-block; padding:2px 10px; border-radius:5px; background:{{ $colorEstado[$reserva->estado] ?? '#6b7280' }}; color:#fff; font-size:11px; font-weight:600; text-align:center; width:fit-content;">
                                {{ $labelEstado[$reserva->estado] ?? $reserva->estado }}
                            </span>
                            <span style="font-size:12px; color:#6b7280;">
                                {{ $reserva->fecha_checkin->format('d/m/Y') }} → {{ $checkoutMostrado }}
                            </span>
                            <span style="text-align:right; font-size:14px; font-weight:700;">{{ $money($totalReal) }}</span>
                        </summary>

                        <div style="padding:4px 8px 20px 44px; border-bottom:1px solid #e5e7eb;">
                            <div style="display:flex; flex-wrap:wrap; gap:24px; margin-bottom:14px; font-size:12px; color:#374151;">
                                <div>🌙 Noches: <strong>{{ $reserva->numero_noches }}</strong></div>
                                <div>👥 Personas: <strong>{{ $reserva->numero_personas ?? '—' }}</strong></div>
                                <div>{{ $climaLabel[$reserva->climatizacion] ?? 'Sin aire/ventilador' }}</div>
                                @if($reserva->huesped_telefono)
                                    <div>📞 {{ $reserva->huesped_telefono }}</div>
                                @endif
                                @if($reserva->huesped_documento)
                                    <div>🪪 {{ $reserva->huesped_documento }}</div>
                                @endif
                            </div>

                            @if($reserva->consumos->isEmpty())
                                <p style="font-size:12px; color:#9ca3af;">Sin productos agregados a esta cuenta.</p>
                            @else
                                <div style="overflow-x:auto; margin-bottom:14px;">
                                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                        <thead>
                                            <tr style="background:#2f3542; color:#fff;">
                                                <th style="padding:6px 8px; text-align:left;">Fecha</th>
                                                <th style="padding:6px 8px; text-align:left;">Producto</th>
                                                <th style="padding:6px 8px;">Cantidad</th>
                                                <th style="padding:6px 8px;">Precio Unit.</th>
                                                <th style="padding:6px 8px;">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($reserva->consumos as $consumo)
                                                <tr style="{{ $loop->even ? 'background:#f1f2f6;' : '' }}">
                                                    <td style="padding:6px 8px; text-align:left;">{{ $consumo->created_at->format('d/m/Y H:i') }}</td>
                                                    <td style="padding:6px 8px; text-align:left;">{{ $consumo->descripcion }}</td>
                                                    <td style="padding:6px 8px; text-align:center;">{{ $cantidadFmt($consumo->cantidad) }}</td>
                                                    <td style="padding:6px 8px; text-align:center;">{{ $money($consumo->precio_unitario) }}</td>
                                                    <td style="padding:6px 8px; text-align:center; font-weight:600;">{{ $money($consumo->subtotal) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div style="display:flex; flex-wrap:wrap; gap:24px; padding-top:10px; border-top:1px solid #e5e7eb; font-size:13px;">
                                <div>Hospedaje: <strong>{{ $money($reserva->total_estimado) }}</strong></div>
                                <div>Productos: <strong>{{ $money($reserva->total_consumos) }}</strong></div>
                                <div>Abono: <strong>{{ $money($reserva->abono_monto) }}</strong></div>
                                <div>{{ $etiquetaTotal }}: <strong style="color:{{ $reserva->factura ? '#065f46' : ($totalReal > 0 ? '#dc2626' : '#065f46') }};">{{ $money($totalReal) }}</strong></div>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament::page>
