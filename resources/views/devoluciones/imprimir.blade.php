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
  </style>
</head>
<body onload="window.print()">
  <h2>{{ $dev->numero_visual }}</h2>
  <div>Fecha: {{ $dev->fecha?->format('Y-m-d H:i') }}</div>
  <div>Cliente: {{ $dev->factura?->cliente?->nombre ?? 'N/A' }}</div>
  <div>Factura origen: {{ 'SAL-' . str_pad((string) $dev->factura_id, 6, '0', STR_PAD_LEFT) }}</div>
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
