<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>{{ $factura->tipo_factura === 'electronica' ? 'Factura electronica' : 'Salida de mercancia' }}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size:13px; color:#111; max-width:21.5cm; margin:0 auto; padding:1.5cm; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #111; padding-bottom:14px; margin-bottom:14px; }
    .logo { max-width:140px; max-height:90px; margin-bottom:8px; }
    .empresa { font-weight:700; font-size:18px; }
    .lema { font-size:12px; color:#555; margin-top:2px; }
    .doc-info { text-align:right; }
    .doc-info .numero { font-size:20px; font-weight:700; }
    .doc-info .tipo { font-size:12px; color:#555; margin-top:2px; }
    .cliente-box { margin-bottom:16px; }
    .cliente-box .label { font-size:11px; color:#666; text-transform:uppercase; letter-spacing:.03em; }
    table.items { width:100%; border-collapse:collapse; margin-bottom:14px; }
    table.items th { text-align:left; border-bottom:2px solid #111; padding:6px 4px; font-size:12px; text-transform:uppercase; color:#333; }
    table.items td { padding:6px 4px; border-bottom:1px solid #ddd; vertical-align:top; }
    table.items td.num, table.items th.num { text-align:right; }
    .totales { width:320px; margin-left:auto; }
    .totales div { display:flex; justify-content:space-between; padding:3px 0; }
    .totales .total-final { border-top:2px solid #111; margin-top:6px; padding-top:8px; font-size:18px; font-weight:700; }
    .factus-box { border:1px solid #ccc; border-radius:6px; padding:12px; margin-top:16px; font-size:12px; background:#fafafa; }
    .break { overflow-wrap:anywhere; word-break:break-word; }
    .qr { display:block; width:150px; height:150px; margin:8px 0; }
    .footer { margin-top:24px; font-size:12px; color:#444; display:flex; justify-content:space-between; }
    .advertencia { text-align:center; font-weight:700; font-size:13px; margin-top:20px; text-transform:uppercase; color:#333; }
    @media print { @page { size:letter; margin:1.2cm; } body { padding:0; } }
  </style>
</head>
<body onload="window.print()">
<div class="header">
  <div>
    @if ($logoUrl)
      <img src="{{ $logoUrl }}" alt="Logo" class="logo"><br>
    @endif
    <div class="empresa">{{ $config->nombre_empresa ?? 'EMPRESA' }}</div>
    <div>NIT {{ $config->nit ?? 'N/A' }}</div>
    @if($config?->lema) <div class="lema">{{ $config->lema }}</div> @endif
    @if($config?->direccion) <div>{{ $config->direccion }}</div> @endif
    @if($config?->telefono) <div>Tel: {{ $config->telefono }}</div> @endif
    @if($esElectronica && ($resolucionDianElectronica || $prefijoElectronico))
      <div style="font-size:11px; color:#555; margin-top:4px;">
        @if($resolucionDianElectronica)
          Resolución DIAN {{ $resolucionDianElectronica }}<br>
        @endif
        @if($prefijoElectronico || $rangoDesdeElectronico || $rangoHastaElectronico)
          {{ $prefijoElectronico ? 'Prefijo '.$prefijoElectronico.' - ' : '' }}Rango autorizado {{ $rangoDesdeElectronico ?? '-' }} al {{ $rangoHastaElectronico ?? '-' }}
        @endif
      </div>
    @endif
  </div>
  <div class="doc-info">
    <div class="numero">{{ $factura->numero_visual }}</div>
    <div class="tipo">{{ $esElectronica ? 'FACTURA ELECTRÓNICA' : 'DOCUMENTO NO FISCAL - SALIDA DE MERCANCÍA' }}</div>
    <div>Fecha: {{ $fechaDoc->format('Y-m-d H:i') }}</div>
    <div>Pago: {{ $esCredito ? 'Crédito' : 'Contado' }}</div>
    @if($esCredito)
      <div>Vence: {{ $vencCarbon ? $vencCarbon->format('Y-m-d') : '-' }}</div>
    @endif
  </div>
</div>

<div class="cliente-box">
  <div class="label">Cliente</div>
  <div><strong>{{ optional($factura->cliente)->nombre ?? 'Sin cliente' }}</strong></div>
  @if(optional($factura->cliente)->identificacion)
    <div>ID: {{ optional($factura->cliente)->identificacion }}</div>
  @endif
</div>

@if($esDomicilio)
  <div class="cliente-box">
    <div class="label">🛵 Domicilio</div>
    @if($factura->dom_nombre) <div>Destinatario: {{ $factura->dom_nombre }}</div> @endif
    @if($factura->dom_telefono) <div>Tel: {{ $factura->dom_telefono }}</div> @endif
    @if($factura->dom_direccion) <div>Dir: {{ $factura->dom_direccion }}</div> @endif
    @if($factura->dom_observaciones) <div>Obs: {{ $factura->dom_observaciones }}</div> @endif
  </div>
@endif

<table class="items">
  <thead>
    <tr>
      <th>#</th>
      <th>Descripción</th>
      <th class="num">Cantidad</th>
      <th class="num">Subtotal</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($factura->detalles as $i => $d)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $d->descripcion_larga }}</td>
        <td class="num">{{ $fmtCant($d->cantidad) }}</td>
        <td class="num">${{ number_format($d->subtotal, 0, ',', '.') }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="totales">
  @if($mostrarIvaSeparado)
    <div><span>Subtotal</span><span>${{ number_format($subtotalBaseSalida, 0, ',', '.') }}</span></div>
    <div><span>IVA</span><span>${{ number_format($ivaTotalSalida, 0, ',', '.') }}</span></div>
  @endif
  @if($costoEmpaque > 0)
    <div><span>Subtotal productos</span><span>${{ number_format($subtotalProductos, 0, ',', '.') }}</span></div>
    @if($esDomicilio)
      @if($costoDesechables > 0)
        <div><span>Desechables</span><span>${{ number_format($costoDesechables, 0, ',', '.') }}</span></div>
      @endif
      <div><span>Domicilio</span><span>${{ number_format($costoDomicilio, 0, ',', '.') }}</span></div>
    @else
      <div><span>Desechables / empaque</span><span>${{ number_format($costoEmpaque, 0, ',', '.') }}</span></div>
    @endif
  @endif
  <div class="total-final"><span>Total</span><span>${{ number_format($factura->total, 0, ',', '.') }}</span></div>
  @if($factura->propina_sugerida > 0)
    {{-- Solo sugerencia para el cliente: no esta incluida en el Total ni se cobra --}}
    <div style="font-size:11px;"><span>Propina sugerida (voluntaria)</span><span>${{ number_format($factura->propina_sugerida, 0, ',', '.') }}</span></div>
  @endif
</div>

@if($factura->observaciones)
  <div style="margin-top:14px;"><strong>Observaciones:</strong> {{ $factura->observaciones }}</div>
@endif

@if($esElectronica)
  <div class="factus-box">
    @if(strtolower((string) $estadoDocumentoElectronico) !== 'validada')
      <div><strong>Estado:</strong> {{ strtoupper($estadoDocumentoElectronico ?: 'pendiente') }}</div>
    @endif
    @if($cufeDocumentoElectronico)
      <div><strong>CUFE:</strong></div>
      <div class="break">{{ $cufeDocumentoElectronico }}</div>
    @endif
    @if($qrImagenDocumentoElectronico)
      <img class="qr" src="{{ trim($qrImagenDocumentoElectronico) }}" alt="QR DIAN">
    @endif
  </div>
@endif

<div class="footer">
  <div>
    @if($nombreVendedorAsignado) <div>Vendedor: {{ $nombreVendedorAsignado }}</div> @endif
    @if($nombreCajero) <div>Cajero: {{ $nombreCajero }}</div> @endif
  </div>
  <div>¡Gracias por su compra!</div>
</div>

<div class="advertencia">
  {{ $esElectronica ? 'Factura electrónica validada' : 'Documento no fiscal - salida de mercancía' }}
</div>

<script>
  window.onload = () => { window.print(); };
</script>
</body>
</html>
