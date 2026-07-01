<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#1f2937; padding:24px; }
    .header { text-align:center; margin-bottom:16px; border-bottom:2px solid #0f766e; padding-bottom:12px; }
    .header h1 { font-size:18px; color:#0f766e; font-weight:900; }
    .sub { font-size:11px; color:#6b7280; margin-top:3px; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th { background:#0f766e; color:white; padding:6px 7px; font-size:10px; text-align:left; }
    td { padding:6px 7px; font-size:10px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    .tr-par td { background:#f8fafc; }
    .badge { display:inline-block; padding:2px 7px; border-radius:99px; font-size:9px; font-weight:700; color:white; }
    .badge-pendiente { background:#f59e0b; }
    .badge-ejecutada  { background:#3b82f6; }
    .badge-cobrada   { background:#16a34a; }
    .badge-cancelada { background:#ef4444; }
    .total-row td { font-weight:800; font-size:12px; color:#0f766e; background:#f0fdf4; border-top:2px solid #0f766e; }
    .footer { margin-top:20px; border-top:1px solid #e5e7eb; padding-top:10px; font-size:9px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $config?->nombre_empresa ?? 'TALLER' }}</h1>
    <div style="font-size:14px; font-weight:700; color:#374151; margin-top:4px;">Reporte de Órdenes de Taller</div>
    <div class="sub">
        @if($estado && $estado !== 'todos')
            Estado: {{ ['pendiente'=>'Pendientes','entregado'=>'Cobradas'][$estado] ?? $estado }}
        @else
            Todas las órdenes
        @endif
        @if($desde || $hasta)
            · Del {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '—' }}
              al {{ $hasta  ? \Carbon\Carbon::parse($hasta)->format('d/m/Y')  : '—' }}
        @endif
        · Generado: {{ now()->format('d/m/Y h:i A') }}
    </div>
</div>

@php
    $labels = ['pendiente'=>'Pendiente','en_proceso'=>'Ejecutada','listo'=>'Ejecutada','entregado'=>'Cobrada','cancelado'=>'Cancelada'];
    $badgeClass = ['pendiente'=>'pendiente','en_proceso'=>'ejecutada','listo'=>'ejecutada','entregado'=>'cobrada','cancelado'=>'cancelada'];
    $granTotal = $query->sum(fn($o) => $o->repuestos->sum('subtotal'));
@endphp

<table>
    <thead>
        <tr>
            <th style="width:40px;">#</th>
            <th style="width:55px;">Orden</th>
            <th>Cliente</th>
            <th style="width:70px;">Placa</th>
            <th>Vehículo</th>
            <th style="width:70px;">Estado</th>
            <th style="width:80px;">Fecha</th>
            <th style="width:80px; text-align:right;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($query as $i => $orden)
        @php $totalOrden = $orden->repuestos->sum('subtotal'); @endphp
        <tr class="{{ $i % 2 === 1 ? 'tr-par' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td style="font-weight:700;">{{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}</td>
            <td>
                <div style="font-weight:600;">{{ $orden->cliente_nombre }}</div>
                @if($orden->cliente_telefono)<div style="color:#6b7280;">{{ $orden->cliente_telefono }}</div>@endif
            </td>
            <td style="font-weight:800; color:#0f766e; font-size:11px;">{{ $orden->placa }}</td>
            <td>
                {{ trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '')) ?: '—' }}
                @if($orden->color)<div style="color:#6b7280;">{{ $orden->color }}</div>@endif
            </td>
            <td>
                <span class="badge badge-{{ $badgeClass[$orden->estado] ?? 'pendiente' }}">
                    {{ $labels[$orden->estado] ?? $orden->estado }}
                </span>
            </td>
            <td>{{ $orden->created_at->format('d/m/Y') }}</td>
            <td style="text-align:right; font-weight:700;">${{ number_format($totalOrden, 0, ',', '.') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:20px;">No hay órdenes para mostrar.</td></tr>
        @endforelse
        @if($query->isNotEmpty())
        <tr class="total-row">
            <td colspan="7" style="text-align:right;">TOTAL GENERAL ({{ $query->count() }} órdenes)</td>
            <td style="text-align:right;">${{ number_format($granTotal, 0, ',', '.') }}</td>
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">
    {{ $config?->nombre_empresa ?? '' }} · Reporte generado el {{ now()->format('d/m/Y h:i A') }}
</div>

</body>
</html>
