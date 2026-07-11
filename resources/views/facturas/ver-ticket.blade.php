<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #111;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      overflow-x: hidden;              /* <-- evita scroll horizontal en el documento */
    }
    .ticket-wrap {
      width: 340px;                    /* ancho típico de ticket */
      max-width: 100%;                 /* no excede el iframe */
      margin: 8px auto;
      padding: 8px 10px;
      box-sizing: border-box;
    }
    /* Si usas <pre> para el contenido, permite salto de línea */
    pre {
      white-space: pre-wrap;           /* <-- envuelve líneas largas */
      word-break: break-word;          /* <-- rompe palabras si toca */
      margin: 0;
    }
    .total { font-weight: 700; }
    .saldo { color: #b91c1c; font-weight: 700; }
  </style>
</head>
<body>
  <div class="ticket">
    {{-- Encabezado --}}
    <div class="center bold">{{ $config->nombre_empresa ?? 'EMPRESA' }}</div>
    <div class="center">NIT {{ $config->nit ?? 'N/A' }}</div>
    <div class="sep"></div>

    {{-- Datos principales --}}
    <div class="center bold">{{ $factura->numero_visual }}</div>
    <div>Cliente: {{ optional($factura->cliente)->nombre ?? 'CONSUMIDOR FINAL' }}</div>
    @if(optional($factura->cliente)->identificacion)
      <div>ID: {{ optional($factura->cliente)->identificacion }}</div>
    @endif
    <div>Fecha: {{ \Carbon\Carbon::parse($factura->fecha)->format('Y-m-d H:i') }}</div>
    @if($factura->fecha_vencimiento)
      <div>Vence: {{ \Carbon\Carbon::parse($factura->fecha_vencimiento)->format('Y-m-d') }}</div>
    @endif
    <div class="sep"></div>

    {{-- Detalle --}}
    <table>
      <thead>
        <tr>
          <th>Descripción</th>
          <th class="tr">Cant</th>
          <th class="tr">Precio</th>
          <th class="tr">Subt.</th>
        </tr>
      </thead>
      <tbody>
        @foreach($factura->detalles as $d)
          <tr>
            <td>{{ $d->descripcion_larga }}</td>
            <td class="tr">{{ (int)$d->cantidad }}</td>
            <td class="tr">${{ number_format($d->precio,0,',','.') }}</td>
            <td class="tr bold">${{ number_format($d->subtotal,0,',','.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="sep"></div>

    @if($config->mostrar_iva_separado ?? false)
      @php
        $idsProductosTicket = $factura->detalles->pluck('producto_id')->filter(fn($id) => is_numeric($id))->unique();
        $ivaPorProductoTicket = \App\Models\Product::where('empresa_id', $factura->empresa_id)
          ->whereIn('id_producto', $idsProductosTicket)
          ->pluck('iva_venta', 'id_producto');

        $subtotalBaseTicket = 0;
        $ivaTotalTicket = 0;
        foreach ($factura->detalles as $d) {
          $ivaPctTicket = (float) ($ivaPorProductoTicket[$d->producto_id] ?? 0);
          $baseLineaTicket = $ivaPctTicket > 0 ? round($d->subtotal / (1 + $ivaPctTicket / 100), 2) : (float) $d->subtotal;
          $subtotalBaseTicket += $baseLineaTicket;
          $ivaTotalTicket += round($d->subtotal - $baseLineaTicket, 2);
        }
      @endphp
    @endif

    {{-- Totales --}}
    <table>
      @if($config->mostrar_iva_separado ?? false)
      <tr>
        <td>Subtotal</td>
        <td class="tr">${{ number_format($subtotalBaseTicket,0,',','.') }}</td>
      </tr>
      <tr>
        <td>IVA</td>
        <td class="tr">${{ number_format($ivaTotalTicket,0,',','.') }}</td>
      </tr>
      @endif
      <tr>
        <td class="bold">Total</td>
        <td class="tr bold">${{ number_format($factura->total,0,',','.') }}</td>
      </tr>
      <tr>
        <td>Saldo</td>
        <td class="tr" style="font-weight:700;color:#b91c1c">${{ number_format($factura->saldo,0,',','.') }}</td>
      </tr>
    </table>

    @if($factura->observaciones)
      <div class="sep"></div>
      <div><span class="bold">Obs:</span> {{ $factura->observaciones }}</div>
    @endif
  </div>
</body>
</html>
