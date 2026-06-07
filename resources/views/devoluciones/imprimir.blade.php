<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{{ $dev->numero_visual }}</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:6px; border-bottom:1px solid #eee; }
    .tot { font-weight: 800; font-size: 14px; text-align:right; }
    .factus-box { border-top:1px dashed #111; border-bottom:1px dashed #111; padding:6px 0; margin:8px 0; }
    .small { font-size: 10px; }
    .break { word-break: break-all; }
  </style>
</head>
<body onload="window.print()">
  @php
    $esNotaCreditoElectronica = filled($dev->factus_credit_note_number) || filled($dev->factus_credit_note_cude);
  @endphp
  <h2>{{ $dev->numero_visual }}</h2>
  <div>Fecha: {{ $dev->fecha?->format('Y-m-d H:i') }}</div>
  <div>Cliente: {{ $dev->factura?->cliente?->nombre ?? 'N/A' }}</div>
  <div>Factura origen: {{ $dev->factura?->numero_visual ?? ('SAL-' . str_pad((string) $dev->factura_id, 6, '0', STR_PAD_LEFT)) }}</div>
  @if($esNotaCreditoElectronica)
    <div class="factus-box small">
      <div><strong>Nota credito electronica:</strong> {{ $dev->factus_credit_note_number ?: 'Pendiente' }}</div>
      <div><strong>Estado:</strong> {{ strtoupper($dev->factus_credit_note_status ?? 'pendiente') }}</div>
      @if($dev->factus_credit_note_validated_at)
        <div><strong>Validada:</strong> {{ $dev->factus_credit_note_validated_at->format('Y-m-d H:i') }}</div>
      @endif
      @if($dev->factus_credit_note_cude)
        <div><strong>CUDE:</strong></div>
        <div class="break">{{ $dev->factus_credit_note_cude }}</div>
      @endif
    </div>
  @endif
  <hr>
  <table>
    <thead>
      <tr>
        <th style="text-align:left">Código</th>
        <th style="text-align:left">Descripción</th>
        <th style="text-align:right">Cant</th>
        <th style="text-align:right">Precio</th>
        <th style="text-align:right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($dev->detalles as $d)
        <tr>
          <td>{{ $d->producto_id }}</td>
          <td>{{ $d->descripcion_larga }}</td>
          <td style="text-align:right">{{ number_format($d->cantidad, 2) }}</td>
          <td style="text-align:right">{{ number_format($d->precio, 2) }}</td>
          <td style="text-align:right">{{ number_format($d->subtotal, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <hr>
  <div class="tot">TOTAL DEVOLUCIÓN: ${{ number_format($dev->total, 2) }}</div>
</body>
</html>
