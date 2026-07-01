<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#1f2937; padding:30px; }
    .header { text-align:center; margin-bottom:20px; border-bottom:2px solid #0f766e; padding-bottom:14px; }
    .header h1 { font-size:20px; color:#0f766e; font-weight:900; }
    .header h2 { font-size:14px; color:#374151; margin-top:4px; }
    .numero { font-size:18px; font-weight:900; color:#0f766e; text-align:right; }
    .seccion { margin-bottom:14px; }
    .seccion-titulo { font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; border-bottom:1px solid #e5e7eb; padding-bottom:3px; margin-bottom:8px; }
    .grid2 { display:table; width:100%; }
    .col { display:table-cell; width:50%; vertical-align:top; padding-right:10px; }
    .campo { margin-bottom:5px; }
    .label { font-size:10px; color:#6b7280; }
    .valor { font-size:13px; font-weight:600; color:#111827; }
    .placa { font-size:24px; font-weight:900; color:#0f766e; letter-spacing:.05em; }
    table { width:100%; border-collapse:collapse; margin-top:6px; }
    th { background:#0f766e; color:white; padding:6px 8px; font-size:10px; text-align:left; }
    td { padding:6px 8px; font-size:11px; border-bottom:1px solid #f1f5f9; }
    .tr-total td { font-weight:800; font-size:13px; color:#0f766e; background:#f0fdf4; border-top:2px solid #0f766e; }
    .badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:10px; font-weight:700; color:white; }
    .badge-pendiente { background:#f59e0b; }
    .badge-ejecutada  { background:#3b82f6; }
    .badge-cobrada   { background:#16a34a; }
    .badge-cancelada { background:#ef4444; }
    .diagnostico { background:#f8fafc; border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px; font-size:12px; line-height:1.5; margin-top:4px; }
    .footer { margin-top:30px; border-top:1px solid #e5e7eb; padding-top:12px; font-size:10px; color:#9ca3af; text-align:center; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $config?->nombre_empresa ?? 'TALLER' }}</h1>
    <h2>Orden de Trabajo</h2>
    @if($config?->direccion)<div style="font-size:11px; color:#6b7280; margin-top:3px;">{{ $config->direccion }}</div>@endif
    @if($config?->telefono)<div style="font-size:11px; color:#6b7280;">Tel: {{ $config->telefono }}</div>@endif
</div>

<div style="display:table; width:100%; margin-bottom:16px;">
    <div style="display:table-cell; vertical-align:middle;">
        @php
            $badges = ['pendiente'=>'pendiente','en_proceso'=>'ejecutada','listo'=>'ejecutada','entregado'=>'cobrada','cancelado'=>'cancelada'];
            $labels = ['pendiente'=>'Pendiente','en_proceso'=>'Ejecutada','listo'=>'Ejecutada','entregado'=>'Cobrada','cancelado'=>'Cancelada'];
        @endphp
        <span class="badge badge-{{ $badges[$orden->estado] ?? 'pendiente' }}">
            {{ $labels[$orden->estado] ?? $orden->estado }}
        </span>
    </div>
    <div style="display:table-cell; text-align:right; vertical-align:middle;">
        <div class="numero">Orden # {{ str_pad($orden->numero_orden, 4, '0', STR_PAD_LEFT) }}</div>
        <div style="font-size:10px; color:#6b7280;">{{ $orden->created_at->format('d/m/Y h:i A') }}</div>
    </div>
</div>

<div class="seccion">
    <div class="seccion-titulo">👤 Cliente</div>
    <div class="grid2">
        <div class="col">
            <div class="campo"><div class="label">Nombre</div><div class="valor">{{ $orden->cliente_nombre }}</div></div>
        </div>
        <div class="col">
            <div class="campo"><div class="label">Teléfono</div><div class="valor">{{ $orden->cliente_telefono ?: '—' }}</div></div>
        </div>
    </div>
</div>

<div class="seccion">
    <div class="seccion-titulo">🚗 Vehículo</div>
    <div class="grid2">
        <div class="col">
            <div class="campo"><div class="label">Placa</div><div class="placa">{{ $orden->placa }}</div></div>
            <div class="campo" style="margin-top:6px;"><div class="label">Marca / Modelo</div><div class="valor">{{ trim(($orden->marca ?? '') . ' ' . ($orden->modelo ?? '')) ?: '—' }}</div></div>
        </div>
        <div class="col">
            <div class="campo"><div class="label">Color</div><div class="valor">{{ $orden->color ?: '—' }}</div></div>
            <div class="campo"><div class="label">Kilometraje</div><div class="valor">{{ $orden->km_ingreso ? number_format($orden->km_ingreso) . ' km' : '—' }}</div></div>
        </div>
    </div>
</div>

@if($orden->diagnostico || $orden->observaciones)
<div class="seccion">
    <div class="seccion-titulo">📋 Diagnóstico</div>
    @if($orden->diagnostico)<div class="diagnostico">{{ $orden->diagnostico }}</div>@endif
    @if($orden->observaciones)<div style="font-size:11px; color:#6b7280; margin-top:4px; font-style:italic;">{{ $orden->observaciones }}</div>@endif
</div>
@endif

<div class="seccion">
    <div class="seccion-titulo">🔩 Repuestos / Servicios</div>
    @if($orden->repuestos->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="text-align:center; width:60px;">Cant.</th>
                <th style="text-align:right; width:90px;">Precio Unit.</th>
                <th style="text-align:right; width:90px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->repuestos as $rep)
            <tr>
                <td>{{ $rep->descripcion }}</td>
                <td style="text-align:center;">{{ $rep->cantidad }}</td>
                <td style="text-align:right;">${{ number_format($rep->precio_unitario, 0, ',', '.') }}</td>
                <td style="text-align:right;">${{ number_format($rep->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="tr-total">
                <td colspan="3" style="text-align:right;">TOTAL</td>
                <td style="text-align:right;">${{ number_format($orden->repuestos->sum('subtotal'), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
    @else
    <div style="color:#9ca3af; font-size:12px; padding:8px 0;">Sin repuestos registrados.</div>
    @endif
</div>

@if($orden->nota_trabajo)
<div class="seccion">
    <div class="seccion-titulo">📝 Nota de trabajo realizado</div>
    <div class="diagnostico">{{ $orden->nota_trabajo }}</div>
</div>
@endif

@if($orden->entregado_at)
<div style="margin-top:10px; font-size:11px; color:#16a34a; font-weight:700;">
    💰 Cobrada el {{ \Carbon\Carbon::parse($orden->entregado_at)->format('d/m/Y h:i A') }}
</div>
@endif

<div class="footer">
    Generado el {{ now()->format('d/m/Y h:i A') }} · {{ $config?->nombre_empresa ?? '' }}
</div>

</body>
</html>
