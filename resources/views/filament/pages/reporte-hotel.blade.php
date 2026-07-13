<x-filament::page>
    @php
        $totalProductos = $this->totalProductos();
        $utilidadProductos = $this->utilidadProductos();
        $totalHospedaje = $this->totalHospedaje();
        $totalGeneral = $totalProductos + $totalHospedaje;
        $utilidadTotal = $utilidadProductos + $totalHospedaje;
        $porHabitacion = $this->reportePorHabitacion();
        $ocupacion = $this->ocupacion();
        $money = fn ($valor) => '$ ' . number_format((float) $valor, 0, ',', '.');
    @endphp

    <div style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; margin-bottom:24px;">
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

    <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
        <div style="flex:1 1 220px; min-width:220px;">
            <x-filament::section class="combo-franja-azul producto-linea-1">
                <div style="font-size:13px; color:#6b7280;">Total Productos</div>
                <div style="font-size:24px; font-weight:700; color:#4338ca;">{{ $money($totalProductos) }}</div>
            </x-filament::section>
        </div>

        <div style="flex:1 1 220px; min-width:220px;">
            <x-filament::section class="combo-franja-azul producto-linea-1">
                <div style="font-size:13px; color:#6b7280;">Total Hospedaje (100% utilidad)</div>
                <div style="font-size:24px; font-weight:700; color:#065f46;">{{ $money($totalHospedaje) }}</div>
            </x-filament::section>
        </div>

        <div style="flex:1 1 220px; min-width:220px;">
            <x-filament::section class="combo-franja-azul producto-linea-1">
                <div style="font-size:13px; color:#6b7280;">Total (Productos + Hospedaje)</div>
                <div style="font-size:24px; font-weight:700; color:#4338ca;">{{ $money($totalGeneral) }}</div>
            </x-filament::section>
        </div>

        <div style="flex:1 1 220px; min-width:220px;">
            <x-filament::section class="combo-franja-azul producto-linea-1">
                <div style="font-size:13px; color:#6b7280;">Utilidad Total</div>
                <div style="font-size:24px; font-weight:700; color:{{ $utilidadTotal < 0 ? '#dc2626' : '#065f46' }};">{{ $money($utilidadTotal) }}</div>
            </x-filament::section>
        </div>

        <div style="flex:1 1 220px; min-width:220px;">
            <x-filament::section class="combo-franja-azul producto-linea-1">
                <div style="font-size:13px; color:#6b7280;">Ocupación del rango</div>
                <div style="font-size:24px; font-weight:700; color:#d97706;">{{ number_format($ocupacion['porcentaje'], 2, ',', '.') }}%</div>
            </x-filament::section>
        </div>
    </div>

    <div style="margin-bottom:24px;">
        <x-filament::section heading="Ingresos por habitación" class="combo-franja-azul producto-linea-1">
            @if(empty($porHabitacion))
                <p style="font-size:13px; color:#6b7280;">No hay hospedajes facturados en este rango de fechas.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; font-size:13px; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                                <th style="padding:8px 16px 8px 0;">Habitación</th>
                                <th style="padding:8px 16px 8px 0;">Reservas</th>
                                <th style="padding:8px 16px 8px 0;">Noches</th>
                                <th style="padding:8px 16px 8px 0;">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($porHabitacion as $fila)
                                @php $filaBg = $loop->odd ? '#eef2ff' : '#fff7ed'; @endphp
                                <tr style="border-bottom:1px solid #f3f4f6; background:{{ $filaBg }};">
                                    <td style="padding:8px 16px 8px 0; font-weight:600;">{{ $fila['habitacion'] }}</td>
                                    <td style="padding:8px 16px 8px 0;">{{ $fila['reservas'] }}</td>
                                    <td style="padding:8px 16px 8px 0;">{{ $fila['noches'] }}</td>
                                    <td style="padding:8px 16px 8px 0;">{{ $money($fila['ingresos']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Ocupación por habitación y día" class="combo-franja-azul producto-linea-1">
        @if($ocupacion['habitaciones']->isEmpty())
            <p style="font-size:13px; color:#6b7280;">No hay habitaciones activas configuradas.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="font-size:11px; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="position:sticky; left:0; background:#eef2ff; padding:4px 12px 4px 0; text-align:left;">Habitación</th>
                            @foreach($ocupacion['dias'] as $dia)
                                <th style="padding:4px 4px; text-align:center; font-weight:400; color:#6b7280;">{{ $dia->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ocupacion['habitaciones'] as $habitacion)
                            @php $filaBg = $loop->odd ? '#eef2ff' : '#fff7ed'; @endphp
                            <tr style="background:{{ $filaBg }};">
                                <td style="position:sticky; left:0; background:{{ $filaBg }}; padding:4px 12px 4px 0; font-weight:600; white-space:nowrap;">{{ $habitacion->numero }}</td>
                                @php
                                    $colorEstado = [
                                        'reservada' => '#93c5fd',
                                        'checkin' => '#fbbf24',
                                        'checkout' => '#22c55e',
                                    ];
                                    $labelEstado = [
                                        'reservada' => 'Reservada (aún no llega)',
                                        'checkin' => 'Ocupada, sin facturar',
                                        'checkout' => 'Ocupada y facturada',
                                    ];
                                @endphp
                                @foreach($ocupacion['dias'] as $dia)
                                    @php $estado = $ocupacion['ocupado'][$habitacion->id][$dia->toDateString()] ?? null; @endphp
                                    <td style="padding:4px 4px; text-align:center;">
                                        <span style="display:inline-block; width:16px; height:16px; border-radius:4px; background:{{ $colorEstado[$estado] ?? '#e5e7eb' }};"
                                            title="{{ $dia->format('d/m/Y') }} — {{ $labelEstado[$estado] ?? 'Libre' }}"></span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px; display:flex; flex-wrap:wrap; align-items:center; gap:16px; font-size:12px; color:#6b7280;">
                <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#22c55e;"></span> Ocupada y facturada</span>
                <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#fbbf24;"></span> Ocupada, sin facturar</span>
                <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#93c5fd;"></span> Reservada (aún no llega)</span>
                <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#e5e7eb;"></span> Libre</span>
            </div>
        @endif
    </x-filament::section>
</x-filament::page>
